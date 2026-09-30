<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeGoal;
use App\Models\PendingChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The two screens the phone did not have, and the state their endpoints were in.
 *
 * Goals: creating one was rejected by the database because the controller wrote
 * status 'active' into an enum of not_started/in_progress/achieved/missed;
 * updating took the id straight off the request and checked nothing, so anybody
 * could rewrite anybody's goal; and the delete route pointed at a method that
 * did not exist.
 *
 * Change approvals had no API at all.
 */
class GoalsAndChangeApprovalApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    private function employeeFor(User $user, string $first = 'Sande'): Employee
    {
        return Employee::create([
            'user_id'    => $user->id,
            'emp_number' => 'MM' . $user->id,
            'first_name' => $first,
            'last_name'  => 'Test',
            'hire_date'  => now()->subYear(),
            'status'     => 'active',
        ]);
    }

    // ── Goals ───────────────────────────────────────────────────────────────

    public function test_a_goal_can_actually_be_created_now(): void
    {
        $user = $this->userWithRole('employee');
        $this->employeeFor($user);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/goals', ['title' => 'Cut loading damage', 'weight' => 20])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Cut loading damage')
            // 'active' is not one of the values the column accepts.
            ->assertJsonPath('data.status', 'not_started')
            ->assertJsonPath('data.progress', 0);

        $this->assertDatabaseHas('employee_goals', [
            'title'  => 'Cut loading damage',
            'status' => 'not_started',
        ]);
    }

    public function test_the_list_comes_back_with_a_summary(): void
    {
        $user     = $this->userWithRole('employee');
        $employee = $this->employeeFor($user);

        EmployeeGoal::create(['employee_id' => $employee->id, 'title' => 'A', 'status' => 'achieved',    'progress' => 100]);
        EmployeeGoal::create(['employee_id' => $employee->id, 'title' => 'B', 'status' => 'in_progress', 'progress' => 50]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/goals')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.achieved', 1)
            ->assertJsonPath('summary.progress', 75);
    }

    public function test_you_only_see_your_own_goals(): void
    {
        $mine   = $this->userWithRole('employee');
        $this->employeeFor($mine);

        $theirs = $this->userWithRole('employee');
        $other  = $this->employeeFor($theirs, 'Ashiraf');
        EmployeeGoal::create(['employee_id' => $other->id, 'title' => 'Theirs', 'status' => 'not_started']);

        $this->actingAs($mine, 'sanctum')
            ->getJson('/api/goals')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_somebody_else_cannot_rewrite_your_goal(): void
    {
        $owner    = $this->userWithRole('employee');
        $employee = $this->employeeFor($owner);
        $goal     = EmployeeGoal::create([
            'employee_id' => $employee->id, 'title' => 'Mine', 'status' => 'not_started', 'progress' => 10,
        ]);

        $stranger = $this->userWithRole('employee');
        $this->employeeFor($stranger, 'Stranger');

        $this->actingAs($stranger, 'sanctum')
            ->putJson("/api/goals/{$goal->id}", ['title' => 'Hijacked', 'progress' => 99])
            ->assertForbidden();

        $this->assertSame('Mine', $goal->fresh()->title);
        $this->assertSame(10, $goal->fresh()->progress);
    }

    public function test_moving_the_bar_moves_the_status_with_it(): void
    {
        $user     = $this->userWithRole('employee');
        $employee = $this->employeeFor($user);
        $goal     = EmployeeGoal::create([
            'employee_id' => $employee->id, 'title' => 'Mine', 'status' => 'not_started', 'progress' => 0,
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/goals/{$goal->id}", ['progress' => 40])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/goals/{$goal->id}", ['progress' => 100])
            ->assertOk()
            ->assertJsonPath('data.status', 'achieved');
    }

    public function test_only_hr_may_delete_a_goal_and_the_route_no_longer_500s(): void
    {
        $user     = $this->userWithRole('employee');
        $employee = $this->employeeFor($user);
        $goal     = EmployeeGoal::create([
            'employee_id' => $employee->id, 'title' => 'Mine', 'status' => 'not_started',
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/goals/{$goal->id}")
            ->assertForbidden();

        $this->actingAs($this->userWithRole('hr-admin'), 'sanctum')
            ->deleteJson("/api/goals/{$goal->id}")
            ->assertOk();

        $this->assertDatabaseMissing('employee_goals', ['id' => $goal->id]);
    }

    // ── Change approvals ────────────────────────────────────────────────────

    private function pendingChange(User $requester, Employee $subject): PendingChange
    {
        return PendingChange::create([
            'requested_by'    => $requester->id,
            'action'          => 'update',
            'model_type'      => 'Employee',
            'model_id'        => $subject->id,
            'label'           => $subject->first_name . ' ' . $subject->last_name,
            'payload'         => ['phone' => '+256700000001'],
            'original_values' => ['phone' => null],
            'status'          => PendingChange::PENDING,
        ]);
    }

    public function test_hr_sees_the_queue_with_its_diff(): void
    {
        $am      = $this->userWithRole('account-manager');
        $subject = $this->employeeFor($this->userWithRole('employee'), 'Ashiraf');
        $change  = $this->pendingChange($am, $subject);

        $hr = $this->userWithRole('hr-admin');

        $this->actingAs($hr, 'sanctum')
            ->getJson('/api/change-approvals')
            ->assertOk()
            ->assertJsonPath('pending_count', 1)
            ->assertJsonPath('data.data.0.label', 'Ashiraf Test')
            ->assertJsonPath('data.data.0.field_count', 1);

        $this->actingAs($hr, 'sanctum')
            ->getJson("/api/change-approvals/{$change->id}")
            ->assertOk()
            ->assertJsonPath('data.diff.0.field', 'phone')
            ->assertJsonPath('data.diff.0.proposed', '+256700000001');
    }

    public function test_nobody_but_hr_may_open_the_queue(): void
    {
        foreach (['account-manager', 'employee', 'payroll-officer', 'md'] as $role) {
            $this->actingAs($this->userWithRole($role), 'sanctum')
                ->getJson('/api/change-approvals')
                ->assertForbidden("{$role} must not see HR's approval queue.");
        }
    }

    public function test_approving_applies_the_change_to_the_real_record(): void
    {
        $am      = $this->userWithRole('account-manager');
        $subject = $this->employeeFor($this->userWithRole('employee'), 'Ashiraf');
        $change  = $this->pendingChange($am, $subject);

        $this->actingAs($this->userWithRole('hr-admin'), 'sanctum')
            ->postJson("/api/change-approvals/{$change->id}/approve")
            ->assertOk()
            ->assertJsonPath('applied', true)
            ->assertJsonPath('pending_count', 0);

        $this->assertSame('+256700000001', $subject->fresh()->phone);
        $this->assertSame(PendingChange::APPROVED, $change->fresh()->status);
    }

    public function test_a_rejection_must_say_why(): void
    {
        $am      = $this->userWithRole('account-manager');
        $subject = $this->employeeFor($this->userWithRole('employee'), 'Ashiraf');
        $change  = $this->pendingChange($am, $subject);
        $hr      = $this->userWithRole('hr-admin');

        // Without a reason the account manager just resubmits the same thing.
        $this->actingAs($hr, 'sanctum')
            ->postJson("/api/change-approvals/{$change->id}/reject", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('review_note');

        $this->actingAs($hr, 'sanctum')
            ->postJson("/api/change-approvals/{$change->id}/reject", ['review_note' => 'Wrong number.'])
            ->assertOk()
            ->assertJsonPath('applied', true);

        $this->assertSame(PendingChange::REJECTED, $change->fresh()->status);
        $this->assertNull($subject->fresh()->phone, 'A rejected change must not touch the record.');
    }

    public function test_a_change_already_decided_cannot_be_decided_again(): void
    {
        $am      = $this->userWithRole('account-manager');
        $subject = $this->employeeFor($this->userWithRole('employee'), 'Ashiraf');
        $change  = $this->pendingChange($am, $subject);
        $hr      = $this->userWithRole('hr-admin');

        $this->actingAs($hr, 'sanctum')->postJson("/api/change-approvals/{$change->id}/approve")->assertOk();

        $this->actingAs($hr, 'sanctum')
            ->postJson("/api/change-approvals/{$change->id}/approve")
            ->assertStatus(409)
            ->assertJsonPath('applied', false);
    }
}
