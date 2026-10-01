<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Department;
use App\Models\JobCategory;
use App\Models\JobPosting;
use App\Models\User;
use App\Services\RecruitmentPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * An application that moves stage tells the applicant.
 *
 * Before this, seven places changed `candidates.status` directly. The column
 * moved, nothing was recorded, and the person who had applied was told
 * nothing at all — so "shortlisted" was a word only the recruiter could see.
 */
class RecruitmentPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function job(?int $categoryId = null): JobPosting
    {
        $department = Department::create(['name' => 'Operations', 'is_active' => true]);

        return JobPosting::create([
            'title'           => 'Security Supervisor',
            'department_id'   => $department->id,
            'job_category_id' => $categoryId,
            'employment_type' => 'full_time',
            'location'        => 'Jinja',
            'description'     => 'Supervise a guard post.',
            'vacancies'       => 2,
            'status'          => 'open',
            'is_public'       => true,
        ]);
    }

    private function candidate(JobPosting $job, array $attributes = []): Candidate
    {
        return Candidate::create(array_merge([
            'job_posting_id' => $job->id,
            'first_name'     => 'Aisha',
            'last_name'      => 'Nambi',
            'email'          => 'aisha.nambi@example.com',
            'phone'          => '0772123456',
            'status'         => 'new',
            'source'         => 'careers_page',
        ], $attributes));
    }

    public function test_every_application_gets_an_unguessable_reference(): void
    {
        $job = $this->job();

        $one = $this->candidate($job);
        $two = $this->candidate($job, ['email' => 'other@example.com']);

        $this->assertSame(12, strlen($one->tracking_code));
        $this->assertNotSame($one->tracking_code, $two->tracking_code);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{12}$/', $one->tracking_code);

        // The code is the only thing standing between a stranger and somebody
        // else's application, so it must be random rather than derived from
        // anything walkable. Checked by generating a batch and requiring them
        // all to differ: a derived code would repeat or run in sequence.
        $codes = collect(range(1, 50))->map(fn () => Candidate::newTrackingCode());
        $this->assertCount(50, $codes->unique(), 'Tracking codes are not random enough to be unguessable.');
    }

    public function test_moving_a_stage_records_it_and_notifies(): void
    {
        Mail::fake();

        $job       = $this->job();
        $candidate = $this->candidate($job);
        $pipeline  = app(RecruitmentPipeline::class);

        $event = $pipeline->moveTo($candidate, 'shortlisted');

        $this->assertNotNull($event);
        $this->assertSame('new', $event->from_status);
        $this->assertSame('shortlisted', $event->to_status);
        $this->assertSame('shortlisted', $candidate->fresh()->status);

        // The applicant is named and told which job, so one of four
        // applications can be told from another.
        $this->assertStringContainsString('Aisha Nambi', $event->message);
        $this->assertStringContainsString('Security Supervisor', $event->message);
        $this->assertStringContainsString($candidate->tracking_code, $event->message);

        // Only channels that actually delivered are recorded. There is no SMS
        // gateway configured, so SMS must not be claimed.
        $this->assertStringContainsString('email', (string) $event->channels);
        $this->assertStringNotContainsString('sms', (string) $event->channels);

        Mail::assertQueued(\App\Mail\ApplicationStatusMail::class);
    }

    public function test_setting_the_same_stage_twice_sends_nothing(): void
    {
        Mail::fake();

        $job       = $this->job();
        $candidate = $this->candidate($job);
        $pipeline  = app(RecruitmentPipeline::class);

        $pipeline->moveTo($candidate, 'shortlisted');
        $again = $pipeline->moveTo($candidate->fresh(), 'shortlisted');

        $this->assertNull($again, 'A re-save must not send a second message.');
        $this->assertSame(1, $candidate->statusEvents()->count());
        Mail::assertQueuedCount(1);
    }

    public function test_a_recruiter_can_hold_the_notice_back(): void
    {
        Mail::fake();

        $job       = $this->job();
        $candidate = $this->candidate($job);

        $event = app(RecruitmentPipeline::class)
            ->moveTo($candidate, 'screening', 'Corrected a mis-keyed stage.', false);

        $this->assertNotNull($event);
        $this->assertSame('screening', $candidate->fresh()->status);
        $this->assertNull($event->channels, 'Nothing went out, so nothing should be recorded.');
        Mail::assertNothingQueued();
    }

    public function test_the_applicant_view_names_the_outcome(): void
    {
        Mail::fake();

        $job      = $this->job();
        $pipeline = app(RecruitmentPipeline::class);

        $hired = $this->candidate($job);
        $pipeline->moveTo($hired, 'hired');
        $hiredStages = collect($pipeline->progressFor($hired->fresh()->load('statusEvents')));
        $this->assertSame('Successful', $hiredStages->firstWhere('key', 'outcome')['label']);
        $this->assertSame('done', $hiredStages->firstWhere('key', 'outcome')['state']);

        $turned = $this->candidate($job, ['email' => 'second@example.com']);
        $pipeline->moveTo($turned, 'rejected');
        $turnedStages = collect($pipeline->progressFor($turned->fresh()->load('statusEvents')));
        $this->assertSame('Not Shortlisted', $turnedStages->firstWhere('key', 'outcome')['label']);
    }

    public function test_an_offer_is_not_a_finished_outcome(): void
    {
        Mail::fake();

        $job       = $this->job();
        $candidate = $this->candidate($job);

        app(RecruitmentPipeline::class)->moveTo($candidate, 'offer');

        $stage = collect(app(RecruitmentPipeline::class)
            ->progressFor($candidate->fresh()->load('statusEvents')))
            ->firstWhere('key', 'outcome');

        // An offer nobody has accepted is still in play.
        $this->assertSame('current', $stage['state']);
    }

    public function test_a_rejection_does_not_read_like_a_promotion(): void
    {
        Mail::fake();

        $job       = $this->job();
        $candidate = $this->candidate($job, ['status' => 'screening']);

        $event = app(RecruitmentPipeline::class)->moveTo($candidate, 'rejected');

        $this->assertStringContainsString('not been shortlisted', $event->message);
        $this->assertStringNotContainsString('congratulations', strtolower($event->message));
    }

    public function test_auto_shortlisting_tells_both_sides(): void
    {
        Mail::fake();

        Role::findOrCreate('recruiter', 'web');
        $recruiter = User::factory()->create(['status' => 'active']);
        $recruiter->assignRole('recruiter');

        $job = $this->job();

        $criteria = \App\Models\ShortlistingCriteria::create([
            'job_posting_id' => $job->id,
            'title'          => 'Guard screening',
            'top_n'          => 1,
            'is_active'      => true,
            'created_by'     => $recruiter->id,
        ]);

        $question = \App\Models\ShortlistingQuestion::create([
            'criteria_id'    => $criteria->id,
            'question'       => 'Do you hold a valid guard licence?',
            'question_type'  => 'yes_no',
            'correct_answer' => 'yes',
            'weight'         => 5,
            'sort_order'     => 1,
        ]);

        $strong = $this->candidate($job, ['email' => 'strong@example.com']);
        $weak   = $this->candidate($job, ['email' => 'weak@example.com']);

        foreach ([[$strong, 'yes'], [$weak, 'no']] as [$candidate, $answer]) {
            \App\Http\Controllers\Recruitment\ShortlistingController::saveResponses(
                $candidate, $criteria, [$question->id => $answer]
            );
        }

        $this->actingAs($recruiter)
            ->post(route('recruitment.shortlisting.auto-shortlist', $job), ['top_n' => 1])
            ->assertRedirect();

        $this->assertSame('shortlisted', $strong->fresh()->status);
        $this->assertSame('rejected', $weak->fresh()->status);

        // The mass update it replaced changed the column and told nobody.
        $this->assertSame(1, $strong->fresh()->statusEvents()->count());
        $this->assertSame(1, $weak->fresh()->statusEvents()->count());
        Mail::assertQueuedCount(2);
    }

    public function test_a_new_posting_only_alerts_the_people_following_that_category(): void
    {
        Mail::fake();

        $security = JobCategory::create(['name' => 'Security', 'slug' => 'security']);
        $cleaning = JobCategory::create(['name' => 'Cleaning', 'slug' => 'cleaning']);

        $follower = \App\Models\JobSeeker::create([
            'name' => 'Follower', 'email' => 'follower@example.com',
            'phone' => '0772000111', 'password' => 'secret-password',
        ]);
        $follower->categories()->sync([$security->id]);

        $other = \App\Models\JobSeeker::create([
            'name' => 'Other', 'email' => 'other@example.com',
            'password' => 'secret-password',
        ]);
        $other->categories()->sync([$cleaning->id]);

        $reached = app(\App\Services\RecruitmentNotifier::class)
            ->newJobPosted($this->job($security->id));

        $this->assertSame(1, $reached);
        $this->assertSame(1, \App\Models\JobSeekerNotification::where('job_seeker_id', $follower->id)->count());
        $this->assertSame(0, \App\Models\JobSeekerNotification::where('job_seeker_id', $other->id)->count());
    }

    public function test_an_uncategorised_posting_alerts_nobody(): void
    {
        Mail::fake();

        $seeker = \App\Models\JobSeeker::create([
            'name' => 'Anyone', 'email' => 'anyone@example.com', 'password' => 'secret-password',
        ]);
        $seeker->categories()->sync([JobCategory::create(['name' => 'Any', 'slug' => 'any'])->id]);

        // Nothing to match on, so nothing is sent — rather than everything
        // being sent to everybody.
        $this->assertSame(0, app(\App\Services\RecruitmentNotifier::class)->newJobPosted($this->job()));
        Mail::assertNothingQueued();
    }
}
