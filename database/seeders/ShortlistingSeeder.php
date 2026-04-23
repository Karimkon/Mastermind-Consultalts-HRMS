<?php
namespace Database\Seeders;

use App\Models\{JobPosting, ShortlistingCriteria, ShortlistingQuestion, ShortlistingResponse, Candidate};
use Illuminate\Database\Seeder;

class ShortlistingSeeder extends Seeder
{
    public function run(): void
    {
        // Find the first open job posting to attach demo criteria
        $job = JobPosting::first();

        if (!$job) {
            $this->command->warn('No job postings found. Skipping ShortlistingSeeder.');
            return;
        }

        // Create criteria for the first job
        $criteria = ShortlistingCriteria::create([
            'job_posting_id' => $job->id,
            'title'          => 'Standard Screening — ' . $job->title,
            'description'    => 'Automated shortlisting questionnaire to identify top candidates based on experience, skills, and cultural fit.',
            'top_n'          => 10,
            'is_active'      => true,
            'created_by'     => 1,
        ]);

        $questions = [
            [
                'question'      => 'How many years of relevant work experience do you have?',
                'question_type' => 'multiple_choice',
                'weight'        => 8,
                'options'       => [
                    ['text' => 'Less than 1 year',     'is_correct' => false],
                    ['text' => '1–2 years',             'is_correct' => false],
                    ['text' => '3–5 years',             'is_correct' => true],
                    ['text' => 'More than 5 years',     'is_correct' => false],
                ],
                'correct_answer' => null,
            ],
            [
                'question'      => 'Do you hold a relevant degree or professional qualification?',
                'question_type' => 'yes_no',
                'weight'        => 7,
                'options'       => null,
                'correct_answer' => 'yes',
            ],
            [
                'question'      => 'Are you immediately available to start?',
                'question_type' => 'yes_no',
                'weight'        => 6,
                'options'       => null,
                'correct_answer' => 'yes',
            ],
            [
                'question'      => 'Rate your proficiency in Microsoft Office / Google Workspace (1 = Beginner, 5 = Expert)',
                'question_type' => 'scale',
                'weight'        => 5,
                'options'       => null,
                'correct_answer' => null,
            ],
            [
                'question'      => 'Have you previously worked in a similar role at a consultancy or staffing firm?',
                'question_type' => 'yes_no',
                'weight'        => 7,
                'options'       => null,
                'correct_answer' => 'yes',
            ],
            [
                'question'      => 'Which best describes your highest level of education?',
                'question_type' => 'multiple_choice',
                'weight'        => 6,
                'options'       => [
                    ['text' => 'High school / Matric',           'is_correct' => false],
                    ['text' => 'Diploma / Certificate',          'is_correct' => false],
                    ['text' => "Bachelor's degree",              'is_correct' => true],
                    ['text' => "Postgraduate / Master's / PhD",  'is_correct' => false],
                ],
                'correct_answer' => null,
            ],
            [
                'question'      => 'Rate your ability to work independently without close supervision (1 = Need daily guidance, 5 = Fully self-directed)',
                'question_type' => 'scale',
                'weight'        => 6,
                'options'       => null,
                'correct_answer' => null,
            ],
            [
                'question'      => 'Are you willing to travel as required by the role?',
                'question_type' => 'yes_no',
                'weight'        => 5,
                'options'       => null,
                'correct_answer' => 'yes',
            ],
            [
                'question'      => 'Which of the following best describes your salary expectation range?',
                'question_type' => 'multiple_choice',
                'weight'        => 4,
                'options'       => [
                    ['text' => 'Below market rate',     'is_correct' => false],
                    ['text' => 'Market related',        'is_correct' => true],
                    ['text' => '10–20% above market',   'is_correct' => false],
                    ['text' => 'More than 20% above',   'is_correct' => false],
                ],
                'correct_answer' => null,
            ],
            [
                'question'      => 'Rate your written and verbal communication skills (1 = Basic, 5 = Excellent)',
                'question_type' => 'scale',
                'weight'        => 7,
                'options'       => null,
                'correct_answer' => null,
            ],
            [
                'question'      => 'Do you have a valid driver\'s licence?',
                'question_type' => 'yes_no',
                'weight'        => 4,
                'options'       => null,
                'correct_answer' => 'yes',
            ],
            [
                'question'      => 'Have you successfully managed projects or teams in a previous role?',
                'question_type' => 'yes_no',
                'weight'        => 6,
                'options'       => null,
                'correct_answer' => 'yes',
            ],
            [
                'question'      => 'Rate your comfort with using digital HR or CRM systems (1 = Never used, 5 = Expert)',
                'question_type' => 'scale',
                'weight'        => 5,
                'options'       => null,
                'correct_answer' => null,
            ],
            [
                'question'      => 'Briefly describe why you are the best candidate for this role. (Reviewed manually)',
                'question_type' => 'text',
                'weight'        => 0,
                'options'       => null,
                'correct_answer' => null,
            ],
        ];

        $questionModels = [];
        foreach ($questions as $i => $q) {
            $questionModels[] = ShortlistingQuestion::create([
                'criteria_id'    => $criteria->id,
                'question'       => $q['question'],
                'question_type'  => $q['question_type'],
                'options'        => $q['options'],
                'correct_answer' => $q['correct_answer'],
                'weight'         => $q['weight'],
                'sort_order'     => $i,
            ]);
        }

        // Attach demo responses — create demo candidates if none exist
        $candidates = Candidate::where('job_posting_id', $job->id)->get();

        if ($candidates->isEmpty()) {
            $this->command->info("No real candidates on job #{$job->id} — creating demo candidates.");
            $demoNames = [
                ['Sipho', 'Nkosi'],
                ['Amara', 'Dlamini'],
                ['Thabo', 'Mokoena'],
                ['Lerato', 'Sithole'],
                ['Zanele', 'Khumalo'],
            ];
            foreach ($demoNames as $name) {
                $candidates[] = Candidate::create([
                    'job_posting_id' => $job->id,
                    'first_name'     => $name[0],
                    'last_name'      => $name[1],
                    'email'          => strtolower($name[0] . '.' . $name[1]) . '@demo.example.com',
                    'phone'          => '+27 6' . rand(10000000, 99999999),
                    'status'         => 'new',
                    'source'         => 'careers_page',
                ]);
            }
            $candidates = collect($candidates);
        }

        $sampleAnswerSets = [
            // High scorer (~85%)
            fn($qs) => $this->buildAnswers($qs, [2, 'yes', 'yes', 5, 'yes', 2, 5, 'yes', 1, 5, 'yes', 'yes', 4, 'Great experience in this field.']),
            // Medium scorer (~55%)
            fn($qs) => $this->buildAnswers($qs, [1, 'yes', 'no', 3, 'no', 1, 3, 'no', 2, 3, 'no', 'no', 2, 'I am motivated and eager to learn.']),
            // Low scorer (~25%)
            fn($qs) => $this->buildAnswers($qs, [0, 'no', 'no', 2, 'no', 0, 2, 'no', 3, 2, 'no', 'no', 1, 'Looking for my first real job.']),
            // Good scorer (~70%)
            fn($qs) => $this->buildAnswers($qs, [2, 'yes', 'no', 4, 'yes', 2, 4, 'yes', 1, 4, 'yes', 'no', 3, 'Strong background in related fields.']),
            // Top scorer (~95%)
            fn($qs) => $this->buildAnswers($qs, [2, 'yes', 'yes', 5, 'yes', 2, 5, 'yes', 1, 5, 'yes', 'yes', 5, 'Extensive experience and proven track record.']),
        ];

        foreach ($candidates->take(5) as $idx => $candidate) {
            $answerSetFn = $sampleAnswerSets[$idx % count($sampleAnswerSets)];
            $answers     = $answerSetFn($questionModels);

            // Calculate score
            $totalScore = 0;
            $maxScore   = 0;
            foreach ($questionModels as $q) {
                $answer = $answers[$q->id] ?? null;
                $result = $q->scoreAnswer($answer);
                $totalScore += $result['earned'];
                $maxScore   += $result['max'];
            }
            $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;

            ShortlistingResponse::updateOrCreate(
                ['candidate_id' => $candidate->id, 'criteria_id' => $criteria->id],
                [
                    'answers'     => $answers,
                    'total_score' => $totalScore,
                    'max_score'   => $maxScore,
                    'percentage'  => $percentage,
                ]
            );
        }

        $this->command->info("ShortlistingSeeder: created criteria with 14 questions and demo responses for job #{$job->id} ({$job->title}).");

        // If there's a second job, create another criteria set for it too
        $job2 = JobPosting::skip(1)->first();
        if ($job2) {
            $criteria2 = ShortlistingCriteria::create([
                'job_posting_id' => $job2->id,
                'title'          => 'Technical Screening — ' . $job2->title,
                'description'    => 'Technical skills assessment for shortlisting candidates based on domain knowledge and problem-solving ability.',
                'top_n'          => 5,
                'is_active'      => true,
                'created_by'     => 1,
            ]);

            $techQuestions = [
                ['question' => 'How many years of technical experience do you have?', 'question_type' => 'multiple_choice', 'weight' => 9, 'options' => [['text' => 'Less than 1 year', 'is_correct' => false], ['text' => '1–3 years', 'is_correct' => false], ['text' => '3–6 years', 'is_correct' => true], ['text' => '6+ years', 'is_correct' => false]], 'correct_answer' => null],
                ['question' => 'Do you have experience with cloud platforms (AWS, Azure, GCP)?', 'question_type' => 'yes_no', 'weight' => 8, 'options' => null, 'correct_answer' => 'yes'],
                ['question' => 'Rate your ability to work under tight deadlines (1–5)', 'question_type' => 'scale', 'weight' => 6, 'options' => null, 'correct_answer' => null],
                ['question' => 'Do you hold any relevant technical certifications?', 'question_type' => 'yes_no', 'weight' => 7, 'options' => null, 'correct_answer' => 'yes'],
                ['question' => 'Rate your problem-solving and analytical thinking skills (1–5)', 'question_type' => 'scale', 'weight' => 8, 'options' => null, 'correct_answer' => null],
                ['question' => 'Have you led a technical team previously?', 'question_type' => 'yes_no', 'weight' => 6, 'options' => null, 'correct_answer' => 'yes'],
                ['question' => 'Which best describes your preferred work environment?', 'question_type' => 'multiple_choice', 'weight' => 4, 'options' => [['text' => 'Office only', 'is_correct' => false], ['text' => 'Hybrid', 'is_correct' => true], ['text' => 'Remote only', 'is_correct' => false], ['text' => 'No preference', 'is_correct' => false]], 'correct_answer' => null],
                ['question' => 'Are you familiar with Agile / Scrum methodologies?', 'question_type' => 'yes_no', 'weight' => 5, 'options' => null, 'correct_answer' => 'yes'],
                ['question' => 'Describe your biggest technical achievement in your career. (Manual review)', 'question_type' => 'text', 'weight' => 0, 'options' => null, 'correct_answer' => null],
            ];

            foreach ($techQuestions as $i => $q) {
                ShortlistingQuestion::create([
                    'criteria_id'    => $criteria2->id,
                    'question'       => $q['question'],
                    'question_type'  => $q['question_type'],
                    'options'        => $q['options'],
                    'correct_answer' => $q['correct_answer'],
                    'weight'         => $q['weight'],
                    'sort_order'     => $i,
                ]);
            }

            $this->command->info("ShortlistingSeeder: created technical criteria for job #{$job2->id} ({$job2->title}).");
        }
    }

    private function buildAnswers(array $questionModels, array $values): array
    {
        $answers = [];
        foreach ($questionModels as $i => $q) {
            $answers[$q->id] = $values[$i] ?? null;
        }
        return $answers;
    }
}
