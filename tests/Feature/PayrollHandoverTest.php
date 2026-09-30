<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Notification;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The handover between stages of the payroll chain.
 *
 * PayrollApprovalChainTest covers who may approve and in what order. This covers
 * the two ways the chain stopped moving anyway once that order was enforced.
 *
 * The first was silence. A run reached "processed" and then simply waited: the
 * only way for HR to discover it was to open the payroll list and look. Eighteen
 * live runs were sitting that way, several of them a month old, with nobody
 * having been told they were holding the queue.
 *
 * The second was a door left open beside the chain. The Account Manager screen
 * had its own "Mark as Paid" form that accepted a run at status "processed" and
 * wrote "paid" straight over it — no HR, no Finance, no MD, and no approver
 * recorded against any of the three. Three live runs went out that way.
 */
class PayrollHandoverTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** month/year is unique, so each run in a test needs its own period. */
    private int $month = 0;

    private function payrollRun(string $status = 'draft', ?int $clientId = null): PayrollRun
    {
        $this->month++;

        return PayrollRun::create([
            'title'     => 'Payroll ' . $this->month . '/2026',
            'month'     => $this->month,
            'year'      => 2026,
            'status'    => $status,
            'client_id' => $clientId,
        ]);
    }

    // ── The door beside the chain ────────────────────────────────────────────

    public function test_an_account_manager_cannot_mark_a_processed_run_as_paid(): void
    {
        $am     = $this->userWithRole('account-manager');
        $client = Client::create([
            'company_name'       => 'Acme Ltd',
            'contact_person'     => 'A Contact',
            'user_id'            => $this->userWithRole('client')->id,
            'account_manager_id' => $am->id,
        ]);
        $run = $this->payrollRun('processed', $client->id);

        $this->actingAs($am)
            ->post("/account-manager/payroll/{$run->id}/mark-paid", [
                'payment_method' => 'bank_transfer',
            ])
            ->assertNotFound();

        $this->assertSame('processed', $run->refresh()->status,
            'A run must not reach "paid" without passing HR, Finance and the MD.');
        $this->assertNull($run->paid_at);
    }

    public function test_the_account_manager_mark_paid_action_refuses_even_if_it_is_routed_again(): void
    {
        Role::findOrCreate('account-manager', 'web');
        $am  = $this->userWithRole('account-manager');
        $run = $this->payrollRun('processed');

        // The route is gone; the method behind it must refuse on its own terms so
        // that re-adding a route fails loudly instead of quietly reopening this.
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $this->actingAs($am);
        app(\App\Http\Controllers\AccountManagerController::class)
            ->payrollMarkPaid(request(), $run);
    }

    public function test_finance_still_marks_a_run_paid_after_the_md_approves(): void
    {
        $finance = $this->userWithRole('payroll-officer');
        $run     = $this->payrollRun('md_approved');
        $run->update(['locked_at' => now(), 'locked_by' => $finance->id]);

        $this->actingAs($finance)
            ->post("/payroll/{$run->id}/mark-paid")
            ->assertRedirect();

        $this->assertSame('paid', $run->refresh()->status);
        $this->assertNotNull($run->paid_at);
    }

    // ── The silence ──────────────────────────────────────────────────────────

    public function test_processing_a_run_notifies_hr(): void
    {
        Mail::fake();
        $hr  = $this->userWithRole('hr-admin');
        $run = $this->payrollRun('draft');

        $this->actingAs($this->userWithRole('super-admin'))
            ->post("/payroll/{$run->id}/process");

        $this->assertDatabaseHas('notifications', [
            'user_id' => $hr->id,
            'type'    => 'payroll_stage',
            'title'   => 'Payroll awaiting HR approval',
        ]);
    }

    public function test_hr_approval_notifies_finance_and_not_the_md(): void
    {
        Mail::fake();
        $hr      = $this->userWithRole('hr-admin');
        $finance = $this->userWithRole('payroll-officer');
        $md      = $this->userWithRole('md');
        $run     = $this->payrollRun('processed');

        $this->actingAs($hr)->post("/payroll/{$run->id}/hr-approve", ['selected_payslips' => []]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $finance->id,
            'title'   => 'Payroll awaiting Finance approval',
        ]);
        $this->assertSame(0, Notification::where('user_id', $md->id)->count(),
            'The MD is told when Finance is done with it, not before.');
    }

    public function test_finance_approval_notifies_the_md(): void
    {
        Mail::fake();
        $finance = $this->userWithRole('payroll-officer');
        $md      = $this->userWithRole('md');
        $run     = $this->payrollRun('hr_approved');

        $this->actingAs($finance)->post("/payroll/{$run->id}/finance-approve", ['selected_payslips' => []]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $md->id,
            'title'   => 'Payroll awaiting MD approval',
        ]);
    }

    public function test_md_approval_sends_the_run_back_to_finance_to_be_paid(): void
    {
        Mail::fake();
        $finance = $this->userWithRole('payroll-officer');
        $md      = $this->userWithRole('md');
        $run     = $this->payrollRun('finance_approved');

        $this->actingAs($md)->post("/payroll/{$run->id}/approve", ['selected_payslips' => []]);

        $this->assertSame('md_approved', $run->refresh()->status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $finance->id,
            'title'   => 'Payroll approved — ready to pay',
        ]);
    }

    public function test_sending_a_run_back_tells_the_stage_that_now_owns_it_and_why(): void
    {
        Mail::fake();
        $hr      = $this->userWithRole('hr-admin');
        $finance = $this->userWithRole('payroll-officer');
        $run     = $this->payrollRun('hr_approved');

        $this->actingAs($finance)
            ->post("/payroll/{$run->id}/send-back", ['comment' => 'Overtime for the night shift is missing.']);

        $this->assertSame('processed', $run->refresh()->status);

        $note = Notification::where('user_id', $hr->id)->where('type', 'payroll_stage')->first();
        $this->assertNotNull($note, 'HR has to be told the run has come back to them.');
        $this->assertStringContainsString('Overtime for the night shift is missing.', $note->body,
            'A send-back without its reason just bounces the run around the chain.');
    }

    // ── The badge that makes the queue visible ───────────────────────────────

    public function test_each_role_is_counted_only_for_the_stage_it_owns(): void
    {
        $this->payrollRun('processed');          // HR's
        $this->payrollRun('hr_approved');        // Finance's
        $this->payrollRun('finance_approved');   // the MD's
        $this->payrollRun('md_approved');        // Finance's again, to pay
        $this->payrollRun('paid');               // nobody's

        $this->assertSame(1, PayrollRun::awaitingCountFor($this->userWithRole('hr-admin')));
        $this->assertSame(2, PayrollRun::awaitingCountFor($this->userWithRole('payroll-officer')));
        $this->assertSame(1, PayrollRun::awaitingCountFor($this->userWithRole('md')));
        $this->assertSame(0, PayrollRun::awaitingCountFor($this->userWithRole('recruiter')));
        $this->assertSame(0, PayrollRun::awaitingCountFor(null));
    }
}
