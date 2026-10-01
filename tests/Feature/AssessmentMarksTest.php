<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Department;
use App\Models\JobPosting;
use App\Models\ShortlistingCriteria;
use App\Models\ShortlistingQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The screening assessment is worth 30 marks, and the marks must add up.
 *
 * Nothing enforced a total before. Weights ran 1-10 across up to fifteen
 * questions, so one job could be scored out of 12 and the next out of 140 —
 * and the two percentages sat side by side on the same shortlist as though
 * they meant the same thing.
 */
class AssessmentMarksTest extends TestCase
{
    use RefreshDatabase;

    private function recruiter(): User
    {
        Role::findOrCreate('hr-admin', 'web');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('hr-admin');

        return $user;
    }

    private function job(): JobPosting
    {
        $department = Department::create(['name' => 'Finance', 'is_active' => true]);

        return JobPosting::create([
            'title'           => 'Financial Analyst',
            'department_id'   => $department->id,
            'employment_type' => 'full_time',
            'description'     => 'Analyse things.',
            'vacancies'       => 3,
            'status'          => 'open',
            'is_public'       => true,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function questions(array $weights): array
    {
        return collect($weights)->map(fn ($w, $i) => [
            'question'       => 'Question ' . ($i + 1),
            'question_type'  => 'yes_no',
            'weight'         => $w,
            'correct_answer' => 'yes',
        ])->all();
    }

    public function test_a_questionnaire_that_does_not_total_thirty_is_refused(): void
    {
        $job = $this->job();

        $response = $this->actingAs($this->recruiter())
            ->post(route('recruitment.shortlisting.store', $job), [
                'title'     => 'Analyst screening',
                'top_n'     => 5,
                'questions' => $this->questions([5, 5, 5]),   // 15, not 30
            ]);

        $response->assertSessionHasErrors('questions');

        // The message has to say the number, not just "invalid": a recruiter
        // with eleven questions open should not have to add them up by hand.
        $message = session('errors')->first('questions');
        $this->assertStringContainsString('must add up to 30', $message);
        $this->assertStringContainsString('allocated 15', $message);
        $this->assertStringContainsString('15 short', $message);
        $this->assertSame(0, ShortlistingCriteria::count());
    }

    public function test_going_over_thirty_is_refused_and_says_by_how_much(): void
    {
        $job = $this->job();

        $this->actingAs($this->recruiter())
            ->post(route('recruitment.shortlisting.store', $job), [
                'title'     => 'Analyst screening',
                'top_n'     => 5,
                'questions' => $this->questions([20, 20]),    // 40
            ])
            ->assertSessionHasErrors('questions');

        $this->assertStringContainsString('10 too many', session('errors')->first('questions'));
    }

    public function test_exactly_thirty_saves(): void
    {
        $job = $this->job();

        $this->actingAs($this->recruiter())
            ->post(route('recruitment.shortlisting.store', $job), [
                'title'     => 'Analyst screening',
                'top_n'     => 5,
                'questions' => $this->questions([10, 10, 5, 5]),
            ])
            ->assertRedirect();

        $criteria = ShortlistingCriteria::with('questions')->firstOrFail();

        $this->assertSame(30, (int) $criteria->total_marks);
        $this->assertSame(30, $criteria->allocatedMarks());
        $this->assertTrue($criteria->isBalanced());
        $this->assertSame(30.0, $criteria->maxScore());
    }

    public function test_a_shortlist_can_be_any_size_the_recruiter_needs(): void
    {
        $job = $this->job();

        // Six fixed choices (5, 10, 15, 20, 25, 30) could not express a
        // posting with hundreds of applicants where a client wants a hundred
        // through to interview.
        $this->actingAs($this->recruiter())
            ->post(route('recruitment.shortlisting.store', $job), [
                'title'     => 'Analyst screening',
                'top_n'     => 100,
                'questions' => $this->questions([30]),
            ])
            ->assertRedirect();

        $this->assertSame(100, (int) ShortlistingCriteria::firstOrFail()->top_n);
    }

    public function test_an_unreasonable_shortlist_size_is_refused(): void
    {
        $job = $this->job();

        foreach ([0, -5, 5000] as $bad) {
            $this->actingAs($this->recruiter())
                ->post(route('recruitment.shortlisting.store', $job), [
                    'title'     => 'Analyst screening',
                    'top_n'     => $bad,
                    'questions' => $this->questions([30]),
                ])
                ->assertSessionHasErrors('top_n');

            $this->app['auth']->forgetGuards();
        }

        $this->assertSame(0, ShortlistingCriteria::count());
    }

    public function test_auto_shortlisting_honours_the_number_asked_for(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $job = $this->job();

        $criteria = ShortlistingCriteria::create([
            'job_posting_id' => $job->id, 'title' => 'Screening',
            'top_n' => 100, 'total_marks' => 30, 'is_active' => true,
        ]);

        $question = ShortlistingQuestion::create([
            'criteria_id' => $criteria->id, 'question' => 'Do you have a CPA?',
            'question_type' => 'yes_no', 'weight' => 30,
            'correct_answer' => 'yes', 'sort_order' => 0,
        ]);

        // Five applicants, all of whom answered.
        foreach (range(1, 5) as $i) {
            $candidate = Candidate::create([
                'job_posting_id' => $job->id,
                'first_name'     => 'Applicant',
                'last_name'      => (string) $i,
                'email'          => "applicant{$i}@example.com",
                'status'         => 'new',
            ]);

            \App\Http\Controllers\Recruitment\ShortlistingController::saveResponses(
                $candidate, $criteria, [$question->id => $i <= 3 ? 'yes' : 'no']
            );
        }

        // Asking for 2 must take 2, not the 3 highest scorers and not all 5.
        $this->actingAs($this->recruiter())
            ->post(route('recruitment.shortlisting.auto-shortlist', $job), ['top_n' => 2])
            ->assertRedirect();

        $this->assertSame(2, Candidate::where('status', 'shortlisted')->count());

        // The rest who completed screening are told they did not make it.
        $this->assertSame(3, Candidate::where('status', 'rejected')->count());
    }
    public function test_free_text_carries_no_marks_and_is_outside_the_total(): void
    {
        $job = $this->job();

        $questions   = $this->questions([15, 15]);
        $questions[] = [
            'question'      => 'Why do you want this role?',
            'question_type' => 'text',
            'weight'        => 7,            // typed in, but text is not marked
        ];

        $this->actingAs($this->recruiter())
            ->post(route('recruitment.shortlisting.store', $job), [
                'title' => 'Analyst screening', 'top_n' => 5, 'questions' => $questions,
            ])
            ->assertRedirect();

        $criteria = ShortlistingCriteria::with('questions')->firstOrFail();
        $text     = $criteria->questions->firstWhere('question_type', 'text');

        // Stored at zero rather than silently costing the applicant 7 marks
        // nobody can award.
        $this->assertSame(0, (int) $text->weight);
        $this->assertFalse($text->isScored());
        $this->assertSame(30, $criteria->allocatedMarks());
        $this->assertSame(30.0, $criteria->maxScore());
    }

    public function test_each_answer_can_be_worth_a_different_amount(): void
    {
        $job = $this->job();

        $this->actingAs($this->recruiter())
            ->post(route('recruitment.shortlisting.store', $job), [
                'title' => 'Analyst screening',
                'top_n' => 5,
                'questions' => [
                    [
                        'question'      => 'How many years of experience do you have?',
                        'question_type' => 'multiple_choice',
                        'weight'        => 10,
                        'options'       => [
                            ['text' => '1-2 years', 'marks' => 2],
                            ['text' => '3-4 years', 'marks' => 6],
                            ['text' => '5+ years',  'marks' => 10],
                        ],
                    ],
                    ['question' => 'Do you have a CPA?', 'question_type' => 'yes_no',
                     'weight' => 20, 'correct_answer' => 'yes'],
                ],
            ])
            ->assertRedirect();

        $question = ShortlistingQuestion::where('question_type', 'multiple_choice')->firstOrFail();

        // Not right-or-wrong: a graded answer earns part of the marks.
        $this->assertSame(2.0, (float) $question->scoreAnswer(0)['earned']);
        $this->assertSame(6.0, (float) $question->scoreAnswer(1)['earned']);
        $this->assertSame(10.0, (float) $question->scoreAnswer(2)['earned']);
        $this->assertSame(10.0, (float) $question->scoreAnswer(0)['max']);

        // The top answer is still flagged correct, so anything reading the
        // old shape agrees with the new one.
        $this->assertTrue($question->options[2]['is_correct']);
        $this->assertFalse($question->options[0]['is_correct']);
    }

    public function test_an_answer_cannot_be_worth_more_than_its_question(): void
    {
        $job = $this->job();

        $this->actingAs($this->recruiter())
            ->post(route('recruitment.shortlisting.store', $job), [
                'title' => 'Analyst screening',
                'top_n' => 5,
                'questions' => [
                    [
                        'question'      => 'Years of experience?',
                        'question_type' => 'multiple_choice',
                        'weight'        => 10,
                        'options'       => [
                            ['text' => '1-2 years', 'marks' => 2],
                            ['text' => '5+ years',  'marks' => 25],   // over
                        ],
                    ],
                    ['question' => 'CPA?', 'question_type' => 'yes_no',
                     'weight' => 20, 'correct_answer' => 'yes'],
                ],
            ])
            ->assertSessionHasErrors('questions.0.options.1.marks');

        $this->assertSame(0, ShortlistingCriteria::count());
    }

    public function test_an_older_questionnaire_still_scores_all_or_nothing(): void
    {
        $job = $this->job();

        $criteria = ShortlistingCriteria::create([
            'job_posting_id' => $job->id, 'title' => 'Old', 'top_n' => 5, 'is_active' => true,
        ]);

        // The shape written before answers carried their own marks.
        $question = ShortlistingQuestion::create([
            'criteria_id'   => $criteria->id,
            'question'      => 'Do you have a degree?',
            'question_type' => 'multiple_choice',
            'weight'        => 8,
            'sort_order'    => 0,
            'options'       => [
                ['text' => 'No',  'is_correct' => false],
                ['text' => 'Yes', 'is_correct' => true],
            ],
        ]);

        $this->assertSame(0.0, (float) $question->scoreAnswer(0)['earned']);
        $this->assertSame(8.0, (float) $question->scoreAnswer(1)['earned']);
    }

    public function test_an_application_cannot_skip_the_questions(): void
    {
        Mail::fake();
        Storage::fake('local');

        $job = $this->job();

        $criteria = ShortlistingCriteria::create([
            'job_posting_id' => $job->id, 'title' => 'Screening',
            'top_n' => 5, 'total_marks' => 30, 'is_active' => true,
        ]);

        foreach ([['Do you have a CPA?', 'yes_no', 20], ['Why this role?', 'text', 0]] as $i => [$q, $type, $w]) {
            ShortlistingQuestion::create([
                'criteria_id' => $criteria->id, 'question' => $q, 'question_type' => $type,
                'weight' => $w, 'correct_answer' => $type === 'yes_no' ? 'yes' : null, 'sort_order' => $i,
            ]);
        }

        // `required` on a radio is a browser courtesy; the server has to say so.
        $this->post(route('careers.apply', $job), [
            'name'  => 'Grace Akello',
            'email' => 'grace.akello@example.com',
            'cv'    => UploadedFile::fake()->create('cv.pdf', 60, 'application/pdf'),
        ])->assertSessionHasErrors('screening');

        $this->assertSame(0, Candidate::count(), 'A half-finished application left a candidate behind.');
    }

    public function test_a_completed_application_is_marked_out_of_thirty(): void
    {
        Mail::fake();
        Storage::fake('local');

        $job = $this->job();

        $criteria = ShortlistingCriteria::create([
            'job_posting_id' => $job->id, 'title' => 'Screening',
            'top_n' => 5, 'total_marks' => 30, 'is_active' => true,
        ]);

        $experience = ShortlistingQuestion::create([
            'criteria_id' => $criteria->id,
            'question'    => 'How many years of experience?',
            'question_type' => 'multiple_choice',
            'weight'      => 10,
            'sort_order'  => 0,
            'options'     => [
                ['text' => '1-2 years', 'marks' => 2,  'is_correct' => false],
                ['text' => '3-4 years', 'marks' => 6,  'is_correct' => false],
                ['text' => '5+ years',  'marks' => 10, 'is_correct' => true],
            ],
        ]);

        $cpa = ShortlistingQuestion::create([
            'criteria_id' => $criteria->id, 'question' => 'Do you have a CPA?',
            'question_type' => 'yes_no', 'weight' => 20,
            'correct_answer' => 'yes', 'sort_order' => 1,
        ]);

        $this->post(route('careers.apply', $job), [
            'name'      => 'Grace Akello',
            'email'     => 'grace.akello@example.com',
            'cv'        => UploadedFile::fake()->create('cv.pdf', 60, 'application/pdf'),
            'screening' => [$experience->id => '1', $cpa->id => 'yes'],
        ])->assertRedirect();

        $response = Candidate::firstOrFail()->shortlistingResponse;

        // 6 for "3-4 years" plus the full 20 for the CPA.
        $this->assertSame(26.0, (float) $response->total_score);
        $this->assertSame(30.0, (float) $response->max_score);
        $this->assertEqualsWithDelta(86.67, (float) $response->percentage, 0.01);
    }
}
