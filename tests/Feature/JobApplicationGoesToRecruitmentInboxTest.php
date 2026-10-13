<?php

namespace Tests\Feature;

use App\Mail\JobApplicationMail;
use App\Models\Candidate;
use App\Models\JobPosting;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A job application goes to one shared inbox, not to people's personal mail.
 *
 * It used to be emailed to every holder of the recruiter, hr-admin and
 * super-admin roles. That put each application into several private inboxes at
 * once: nobody could tell whether a candidate had already been answered,
 * somebody on leave took their copy with them, and a recruiter joining later
 * started with no history at all.
 *
 * The in-app notification still reaches all of them, because that is how the
 * dashboard shows work waiting. Only the email moved.
 */
class JobApplicationGoesToRecruitmentInboxTest extends TestCase
{
    use RefreshDatabase;

    private const INBOX = 'recruit@mastermindconsults.co.ug';

    private function staffWith(string $role, string $email): User
    {
        Role::findOrCreate($role, 'web');
        $u = User::factory()->create(['status' => 'active', 'email' => $email]);
        $u->assignRole($role);

        return $u;
    }

    private function application(): Candidate
    {
        $job = JobPosting::create([
            'title' => 'Security Officer',
            'description' => 'Guarding a site in Kampala.',
            'status' => 'open',
        ]);

        return Candidate::create([
            'job_posting_id' => $job->id,
            'first_name' => 'Ambreen',
            'last_name' => 'Nakato',
            'email' => 'ambreen@example.test',
            'phone' => '+256771234567',
            'status' => 'applied',
        ]);
    }

    public function test_the_email_goes_to_the_recruitment_inbox(): void
    {
        Mail::fake();

        $this->staffWith('hr-admin', 'hr@mastermindconsults.co.ug');

        app(NotificationService::class)->newApplication($this->application());

        Mail::assertQueued(JobApplicationMail::class, fn ($m) => $m->hasTo(self::INBOX));
    }

    public function test_and_not_to_hr_personally(): void
    {
        // The point of the change. Before this it landed in their own mailbox.
        Mail::fake();

        $this->staffWith('hr-admin', 'hr@mastermindconsults.co.ug');
        $this->staffWith('recruiter', 'recruiter@mastermindconsults.co.ug');
        $this->staffWith('super-admin', 'admin@mastermindconsults.co.ug');

        app(NotificationService::class)->newApplication($this->application());

        foreach (['hr@', 'recruiter@', 'admin@'] as $prefix) {
            Mail::assertNotQueued(
                JobApplicationMail::class,
                fn ($m) => $m->hasTo($prefix.'mastermindconsults.co.ug'),
            );
        }
    }

    public function test_exactly_one_email_is_sent_however_many_recruiters_there_are(): void
    {
        Mail::fake();

        $this->staffWith('hr-admin', 'hr@mastermindconsults.co.ug');
        $this->staffWith('recruiter', 'r1@mastermindconsults.co.ug');
        $this->staffWith('recruiter', 'r2@mastermindconsults.co.ug');

        app(NotificationService::class)->newApplication($this->application());

        Mail::assertQueuedCount(1);
    }

    public function test_everybody_still_gets_the_in_app_notification(): void
    {
        // The dashboard is how they see work waiting; that did not move.
        Mail::fake();

        $hr = $this->staffWith('hr-admin', 'hr@mastermindconsults.co.ug');
        $rec = $this->staffWith('recruiter', 'r1@mastermindconsults.co.ug');

        app(NotificationService::class)->newApplication($this->application());

        foreach ([$hr, $rec] as $u) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $u->id,
                'type' => 'new_application',
            ]);
        }
    }

    public function test_the_address_can_be_repointed_without_a_deploy(): void
    {
        // It is config-driven on purpose: a shared address changes more often
        // than code does.
        Mail::fake();
        config(['mail.recruitment_inbox' => 'jobs@example.test']);

        $this->staffWith('hr-admin', 'hr@mastermindconsults.co.ug');

        app(NotificationService::class)->newApplication($this->application());

        Mail::assertQueued(JobApplicationMail::class, fn ($m) => $m->hasTo('jobs@example.test'));
    }

    public function test_an_unset_inbox_sends_nothing_rather_than_failing_the_application(): void
    {
        // A misconfigured address must not cost somebody their application.
        Mail::fake();
        config(['mail.recruitment_inbox' => null]);

        $this->staffWith('hr-admin', 'hr@mastermindconsults.co.ug');

        app(NotificationService::class)->newApplication($this->application());

        Mail::assertNothingQueued();
        $this->assertDatabaseHas('notifications', ['type' => 'new_application']);
    }
}
