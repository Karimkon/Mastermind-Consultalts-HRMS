<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PayrollComment;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The way out of a run that went the wrong way.
 *
 * Once a run reached "paid" the screen offered nothing at all — no correction,
 * no deletion, no way past a stage whose owner has left. Three live runs were
 * paid with no approver against any stage, and there was no way to pull them
 * back.
 *
 * This walks past the entire approval chain, so the tests that matter most are
 * the ones about who may do it and what it leaves behind.
 */
class PayrollOverrideTest extends TestCase
{
    use RefreshDatabase;

    private int $month = 0;

    private function userWithRoles(string ...$roles): User
    {
        $u = User::factory()->create();
        foreach ($roles as $r) {
            Role::findOrCreate($r, 'web');
            $u->assignRole($r);
        }

        return $u;
    }

    private function payrollRun(string $status = 'paid'): PayrollRun
    {
        $this->month++;

        return PayrollRun::create([
            'title'  => "Payroll {$this->month}/2026",
            'month'  => $this->month,
            'year'   => 2026,
            'status' => $status,
        ]);
    }

    // ── Who may ─────────────────────────────────────────────────────────────

    public function test_a_super_admin_can_move_a_paid_run_back(): void
    {
        $run = $this->payrollRun('paid');
        $run->update(['paid_at' => now(), 'locked_at' => now()]);

        $this->actingAs($this->userWithRoles('super-admin'))
            ->post("/payroll/{$run->id}/override-status", [
                'status' => 'processed',
                'reason' => 'Paid in error by the old AM screen — pulling back to HR to redo.',
            ])
            ->assertRedirect();

        $run->refresh();
        $this->assertSame('processed', $run->status);
        $this->assertNull($run->paid_at, 'Moving off paid must clear the payment stamp.');
        $this->assertNull($run->locked_at, 'A run before MD approval must not stay locked, or it is stranded.');
    }

    public function test_nobody_below_super_admin_may_override(): void
    {
        foreach (['hr-admin', 'payroll-officer', 'account-manager', 'manager', 'employee'] as $role) {
            $run = $this->payrollRun('paid');

            $this->actingAs($this->userWithRoles($role))
                ->post("/payroll/{$run->id}/override-status", ['status' => 'draft', 'reason' => 'Because.'])
                ->assertForbidden("{$role} must not be able to walk past the approval chain.");

            $this->assertSame('paid', $run->fresh()->status);
        }
    }

    public function test_an_md_is_refused_even_holding_super_admin(): void
    {
        // The CEO holds both. The person who gives final approval must not also
        // be the person who can grant it without one — the same rule denyMd()
        // enforces everywhere else in this controller.
        $run = $this->payrollRun('finance_approved');

        $this->actingAs($this->userWithRoles('super-admin', 'md'))
            ->post("/payroll/{$run->id}/override-status", [
                'status' => 'md_approved',
                'reason' => 'Approving my own run.',
            ])
            ->assertForbidden();

        $this->assertSame('finance_approved', $run->fresh()->status);
    }

    // ── What it leaves behind ───────────────────────────────────────────────

    public function test_the_reason_is_required_and_recorded(): void
    {
        $run   = $this->payrollRun('paid');
        $admin = $this->userWithRoles('super-admin');

        $this->actingAs($admin)
            ->post("/payroll/{$run->id}/override-status", ['status' => 'draft'])
            ->assertSessionHasErrors('reason');

        $this->assertSame('paid', $run->fresh()->status, 'No reason, no move.');

        $this->actingAs($admin)
            ->post("/payroll/{$run->id}/override-status", [
                'status' => 'draft',
                'reason' => 'Created against the wrong client.',
            ])
            ->assertRedirect();

        // The run carries its own history, in the same trail the send-backs use.
        $comment = PayrollComment::where('payroll_run_id', $run->id)->where('action', 'override')->first();
        $this->assertNotNull($comment);
        $this->assertSame('paid', $comment->from_status);
        $this->assertSame('draft', $comment->to_status);
        $this->assertSame('Created against the wrong client.', $comment->comment);

        $this->assertDatabaseHas('audit_logs', [
            'action'   => 'payroll.override_status',
            'user_id'  => $admin->id,
            'model_id' => $run->id,
        ]);
    }

    public function test_it_never_invents_an_approval_that_did_not_happen(): void
    {
        $run   = $this->payrollRun('draft');
        $admin = $this->userWithRoles('super-admin');

        $this->actingAs($admin)
            ->post("/payroll/{$run->id}/override-status", [
                'status' => 'md_approved',
                'reason' => 'HR and Finance both signed off on paper while the system was down.',
            ])
            ->assertRedirect();

        $run->refresh();
        $this->assertSame('md_approved', $run->status);

        // Pushed forward past HR and Finance, but their stamps stay empty —
        // the run must not claim approvals nobody gave.
        $this->assertNull($run->hr_approved_by);
        $this->assertNull($run->finance_approved_by);
        $this->assertNull($run->md_approved_by);
        $this->assertNotNull($run->locked_at, 'MD approval locks, however it was reached.');
    }

    public function test_moving_backwards_clears_only_the_stages_left_behind(): void
    {
        $someone = $this->userWithRoles('hr-admin');
        $run     = $this->payrollRun('md_approved');
        $run->update([
            'hr_approved_by'      => $someone->id, 'hr_approved_at'      => now(),
            'finance_approved_by' => $someone->id, 'finance_approved_at' => now(),
            'md_approved_by'      => $someone->id, 'md_approved_at'      => now(),
        ]);

        $this->actingAs($this->userWithRoles('super-admin'))
            ->post("/payroll/{$run->id}/override-status", [
                'status' => 'hr_approved',
                'reason' => 'Finance approved the wrong figures.',
            ])
            ->assertRedirect();

        $run->refresh();
        $this->assertSame($someone->id, $run->hr_approved_by, 'HR really did approve — leave it.');
        $this->assertNull($run->finance_approved_by);
        $this->assertNull($run->md_approved_by);
    }

    public function test_moving_a_run_to_where_it_already_is_does_nothing(): void
    {
        $run = $this->payrollRun('processed');

        $this->actingAs($this->userWithRoles('super-admin'))
            ->post("/payroll/{$run->id}/override-status", ['status' => 'processed', 'reason' => 'No change.'])
            ->assertSessionHas('error');

        $this->assertSame(0, PayrollComment::where('payroll_run_id', $run->id)->count());
    }

    public function test_a_status_outside_the_chain_is_refused(): void
    {
        $run = $this->payrollRun('paid');

        $this->actingAs($this->userWithRoles('super-admin'))
            ->post("/payroll/{$run->id}/override-status", ['status' => 'whatever', 'reason' => 'Trying it on.'])
            ->assertSessionHasErrors('status');
    }

    // ── Locking and deleting ────────────────────────────────────────────────

    public function test_a_super_admin_may_lock_at_any_stage_but_others_may_not(): void
    {
        $early = $this->payrollRun('processed');
        $this->actingAs($this->userWithRoles('hr-admin'))
            ->post("/payroll/{$early->id}/lock")
            ->assertRedirect();
        $this->assertNull($early->fresh()->locked_at, 'Locking early strands the run for everybody else.');

        $this->actingAs($this->userWithRoles('super-admin'))
            ->post("/payroll/{$early->id}/lock")
            ->assertRedirect();
        $this->assertNotNull($early->fresh()->locked_at);
    }

    public function test_only_a_super_admin_can_delete_a_locked_run_and_it_is_logged(): void
    {
        $run = $this->payrollRun('paid');
        $run->update(['locked_at' => now()]);

        $this->actingAs($this->userWithRoles('hr-admin'))
            ->delete("/payroll/{$run->id}")
            ->assertRedirect();
        $this->assertModelExists($run);

        $admin = $this->userWithRoles('super-admin');
        $this->actingAs($admin)->delete("/payroll/{$run->id}")->assertRedirect();

        $this->assertModelMissing($run);
        // The run is gone, so the record of its going has to live elsewhere.
        $this->assertDatabaseHas('audit_logs', [
            'action'   => 'payroll.deleted',
            'user_id'  => $admin->id,
            'model_id' => $run->id,
        ]);
        $this->assertNotNull(AuditLog::where('action', 'payroll.deleted')->first()->old_values['title'] ?? null);
    }
}
