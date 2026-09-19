<?php

namespace Tests\Feature;

use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Who may release the payroll, and in what order.
 *
 * Paying people is the one action in this system that moves money out of the
 * company, and the control against one person doing it alone is a chain of three
 * approvals by three different roles: HR confirms the figures, Finance confirms the
 * company can pay them, the MD releases the money.
 *
 * The web enforced that chain from the beginning. The mobile API did not: a single
 * `approve` endpoint took a run straight from `processed` to `approved` and locked
 * it, on the authority of one hr-admin. Finance and the MD were skipped, their
 * timestamp columns stayed null, and the result was a locked payroll with no
 * approval trail behind it.
 *
 * A control that exists on one client and not another is not a control. These tests
 * exist so the two cannot drift apart again.
 */
class PayrollApprovalChainTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function payrollRun(string $status = 'processed'): PayrollRun
    {
        return PayrollRun::create([
            'title' => 'September 2026',
            'month' => 9,
            'year' => 2026,
            'status' => $status,
        ]);
    }

    public function test_hr_cannot_skip_finance_and_the_md(): void
    {
        $run = $this->payrollRun('processed');

        // The exact call the old endpoint accepted, from the role it accepted it from.
        $this->actingAs($this->userWithRole('hr-admin'), 'sanctum')
            ->postJson("/api/payroll/{$run->id}/approve")
            ->assertForbidden();

        $run->refresh();

        $this->assertSame('processed', $run->status);
        $this->assertNull($run->locked_at, 'A single HR approval must never lock the payroll.');
        $this->assertNull($run->approved_at);
    }

    public function test_finance_cannot_approve_before_hr(): void
    {
        $run = $this->payrollRun('processed');

        $this->actingAs($this->userWithRole('payroll-officer'), 'sanctum')
            ->postJson("/api/payroll/{$run->id}/finance-approve")
            ->assertStatus(422);

        $this->assertSame('processed', $run->refresh()->status);
    }

    public function test_the_md_cannot_approve_before_finance(): void
    {
        $run = $this->payrollRun('hr_approved');

        $this->actingAs($this->userWithRole('md'), 'sanctum')
            ->postJson("/api/payroll/{$run->id}/approve")
            ->assertStatus(422);

        $this->assertSame('hr_approved', $run->refresh()->status);
        $this->assertNull($run->refresh()->locked_at);
    }

    public function test_hr_cannot_do_the_finance_step(): void
    {
        $run = $this->payrollRun('hr_approved');

        $this->actingAs($this->userWithRole('hr-admin'), 'sanctum')
            ->postJson("/api/payroll/{$run->id}/finance-approve")
            ->assertForbidden();
    }

    public function test_finance_cannot_do_the_md_step(): void
    {
        $run = $this->payrollRun('finance_approved');

        $this->actingAs($this->userWithRole('payroll-officer'), 'sanctum')
            ->postJson("/api/payroll/{$run->id}/approve")
            ->assertForbidden();

        $this->assertNull($run->refresh()->locked_at);
    }

    /**
     * The whole chain, in order, each stage leaving its own record.
     *
     * The timestamps are asserted individually because their absence was the
     * clearest symptom of the bypass: a run could reach `approved` with all three
     * of them null, and nothing downstream could say who had agreed to it.
     */
    public function test_the_full_chain_releases_and_locks_the_payroll(): void
    {
        $run = $this->payrollRun('processed');

        $this->actingAs($this->userWithRole('hr-admin'), 'sanctum')
            ->postJson("/api/payroll/{$run->id}/hr-approve")
            ->assertOk();

        $run->refresh();
        $this->assertSame('hr_approved', $run->status);
        $this->assertNotNull($run->hr_approved_at);
        $this->assertNull($run->locked_at, 'Nothing locks until the MD releases it.');

        $this->actingAs($this->userWithRole('payroll-officer'), 'sanctum')
            ->postJson("/api/payroll/{$run->id}/finance-approve")
            ->assertOk();

        $run->refresh();
        $this->assertSame('finance_approved', $run->status);
        $this->assertNotNull($run->finance_approved_at);
        $this->assertNull($run->locked_at);

        $this->actingAs($this->userWithRole('md'), 'sanctum')
            ->postJson("/api/payroll/{$run->id}/approve")
            ->assertOk();

        $run->refresh();
        $this->assertSame('md_approved', $run->status);
        $this->assertNotNull($run->md_approved_at);
        $this->assertNotNull($run->locked_at, 'MD approval is what locks the run.');

        // All three signatures are on the record, which is the entire point.
        $this->assertNotNull($run->hr_approved_by);
        $this->assertNotNull($run->finance_approved_by);
        $this->assertNotNull($run->md_approved_by);
    }

    /** An employee with no payroll role gets nowhere at any stage. */
    public function test_an_ordinary_employee_may_not_approve_anything(): void
    {
        $run = $this->payrollRun('finance_approved');
        $user = $this->userWithRole('employee');

        foreach (['hr-approve', 'finance-approve', 'approve'] as $stage) {
            $this->actingAs($user, 'sanctum')
                ->postJson("/api/payroll/{$run->id}/{$stage}")
                ->assertForbidden();
        }

        $this->assertNull($run->refresh()->locked_at);
    }
}
