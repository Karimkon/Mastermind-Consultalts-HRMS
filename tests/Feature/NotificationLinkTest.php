<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A notification that cannot be opened is a dead end.
 *
 * The bell's row has carried an href since the appraisal fix, but it read
 * `data.url` — and almost nothing writes one. On the live system 34 of 546
 * notices had a url and 512 did not, "Payroll awaiting HR approval" among
 * them: the one notice whose whole purpose is to send HR to a run it has to
 * approve went nowhere at all.
 *
 * Every type does carry the id of the thing it is about, so the link is
 * derived from that rather than backfilled onto the rows — which fixes the
 * 512 already written as well as everything written from here on.
 */
class NotificationLinkTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name' => 'Ian', 'email' => uniqid('n') . '@test.local', 'password' => bcrypt('secret'),
        ]);
    }

    private function notice(string $type, array $data, ?User $u = null): Notification
    {
        return Notification::create([
            'user_id' => ($u ?? $this->user())->id,
            'type'    => $type,
            'title'   => ucfirst(str_replace('_', ' ', $type)),
            'body'    => 'body',
            'data'    => $data,
        ]);
    }

    // ===== the notice that started this =====

    public function test_a_payroll_stage_notice_opens_the_run_it_is_about(): void
    {
        $n = $this->notice('payroll_stage', ['payroll_run_id' => 33]);

        $this->assertSame(route('payroll.show', 33, false), $n->link);
    }

    public function test_a_payslip_notice_opens_the_employees_own_payslips(): void
    {
        // Addressed to the employee, so it goes to their list and not to the
        // payroll officer's per-slip route, which they cannot open.
        $n = $this->notice('payroll_processed', ['payslip_id' => 1156]);

        $this->assertSame(route('employee.payslips', [], false), $n->link);
    }

    public function test_leave_notices_open_the_leave_request(): void
    {
        foreach (['leave_submitted', 'leave_status', 'leave_adjusted', 'leave_cover_nominated'] as $type) {
            $n = $this->notice($type, ['leave_id' => 12]);
            $this->assertSame(route('leaves.show', 12, false), $n->link, $type . ' should link to the leave');
        }
    }

    public function test_a_meeting_invite_opens_the_meeting(): void
    {
        $n = $this->notice('meeting_invite', ['meeting_id' => 4]);

        $this->assertSame(route('meetings.show', 4, false), $n->link);
    }

    public function test_a_new_application_opens_the_candidate(): void
    {
        $n = $this->notice('new_application', ['candidate_id' => 9, 'job_id' => 3]);

        $this->assertSame(route('recruitment.candidates.show', 9, false), $n->link);
    }

    // ===== stored urls: kept when useful, overruled when stale =====

    public function test_a_stored_url_is_used_where_nothing_can_be_derived(): void
    {
        // quality_alert has no id of its own, only the url it was written with.
        $n = $this->notice('quality_alert', ['url' => 'https://mastermindconsults.co.ug/quality/nonconformities/4']);

        $this->assertSame('https://mastermindconsults.co.ug/quality/nonconformities/4', $n->link);
    }

    public function test_a_derived_link_beats_a_stored_url_pointing_at_the_old_host(): void
    {
        // Every live pip notice stores an absolute mastermind.autos url, a host
        // this system no longer answers on. The derived route is preferred
        // precisely so those stop leading off the site.
        $n = $this->notice('pip', ['pip_id' => 3, 'url' => 'https://mastermind.autos/pips/3']);

        $this->assertSame(route('pips.show', 3, false), $n->link);
        $this->assertStringNotContainsString('mastermind.autos', (string) $n->link);
    }

    // ===== and it never guesses =====

    public function test_a_notice_with_neither_a_mapping_nor_a_url_has_no_link(): void
    {
        $n = $this->notice('something_unmapped', ['whatever' => 1]);

        $this->assertNull($n->link);
    }

    public function test_a_mapped_type_missing_its_id_has_no_link_rather_than_a_broken_one(): void
    {
        // route() would throw inside the bell, which would blank the panel.
        $n = $this->notice('payroll_stage', []);

        $this->assertNull($n->link);
    }

    public function test_null_data_does_not_break_the_accessor(): void
    {
        $n = Notification::create([
            'user_id' => $this->user()->id,
            'type' => 'chat', 'title' => 'New message', 'body' => 'b', 'data' => null,
        ]);

        // chat takes no parameter, so it still resolves.
        $this->assertSame(route('chat.index', [], false), $n->link);
    }

    // ===== what the bell is actually served =====

    public function test_the_bell_sends_the_link_and_the_read_state(): void
    {
        $u = $this->user();
        $unread = $this->notice('payroll_stage', ['payroll_run_id' => 33], $u);
        $read   = $this->notice('leave_submitted', ['leave_id' => 12], $u);
        $read->update(['read_at' => now()]);

        $payload = $this->actingAs($u)->getJson('/ajax/notifications')->assertOk()->json();

        $this->assertSame(1, $payload['unread'], 'only the unread one counts');

        $byId = collect($payload['notifications'])->keyBy('id');

        $this->assertSame(route('payroll.show', 33, false), $byId[$unread->id]['link']);
        $this->assertNull($byId[$unread->id]['read_at']);

        $this->assertSame(route('leaves.show', 12, false), $byId[$read->id]['link']);
        $this->assertNotNull($byId[$read->id]['read_at']);
    }
}
