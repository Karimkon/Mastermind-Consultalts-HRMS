<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\OnboardingTask;
use App\Models\Pip;
use App\Models\PublicHoliday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Holiday pay, onboarding and PIPs, as the phone now sees them.
 *
 * The holiday-pay rules are the ones with money behind them, so they get the most
 * attention here: two decisions by two different people, and payroll pays double
 * only where both are true.
 *
 * The other two are read-mostly on a handset, and what matters is that somebody
 * cannot read a colleague's record by passing an id.
 */
class HolidayPayAndDevelopmentTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function employeeFor(User $user): Employee
    {
        return Employee::create([
            'user_id' => $user->id,
            'emp_number' => 'EMP-'.$user->id,
            'first_name' => 'Joan',
            'last_name' => 'Akello',
            'hire_date' => '2025-03-01',
            'status' => 'active',
        ]);
    }

    private function holiday(): PublicHoliday
    {
        $date = now()->startOfYear()->addMonths(9);

        return PublicHoliday::create([
            'name' => 'Independence Day',
            'date' => $date->toDateString(),
            // Stored alongside the date so a year can be listed without a
            // function on the column.
            'year' => $date->year,
        ]);
    }

    // ── Holiday pay ──────────────────────────────────────────────────────

    /**
     * The decision costs money — double pay for everyone who worked — so it is
     * not something an account manager or an employee may take.
     */
    public function test_only_hr_may_decide_whether_a_holiday_is_paid(): void
    {
        $holiday = $this->holiday();

        foreach (['employee', 'account-manager'] as $role) {
            $this->actingAs($this->user($role), 'sanctum')
                ->postJson("/api/holiday-pay/{$holiday->id}/decide", ['status' => 'approved'])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('holiday_pay_approvals', 0);
    }

    public function test_hr_can_approve_a_holiday_for_payment(): void
    {
        $holiday = $this->holiday();

        $this->actingAs($this->user('hr-admin'), 'sanctum')
            ->postJson("/api/holiday-pay/{$holiday->id}/decide", [
                'status' => 'approved',
                'note' => 'Board agreed on 2 September.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('holiday_pay_approvals', [
            'public_holiday_id' => $holiday->id,
            'status' => 'approved',
        ]);
    }

    /** Deciding twice corrects the decision rather than stacking two of them. */
    public function test_a_second_decision_replaces_the_first(): void
    {
        $holiday = $this->holiday();
        $hr = $this->user('hr-admin');

        $this->actingAs($hr, 'sanctum')
            ->postJson("/api/holiday-pay/{$holiday->id}/decide", ['status' => 'approved'])->assertOk();
        $this->actingAs($hr, 'sanctum')
            ->postJson("/api/holiday-pay/{$holiday->id}/decide", ['status' => 'rejected'])->assertOk();

        $this->assertDatabaseCount('holiday_pay_approvals', 1);
        $this->assertDatabaseHas('holiday_pay_approvals', ['status' => 'rejected']);
    }

    public function test_the_roster_refuses_to_guess_which_client(): void
    {
        $holiday = $this->holiday();

        $this->actingAs($this->user('account-manager'), 'sanctum')
            ->getJson("/api/holiday-pay/{$holiday->id}/roster")
            ->assertStatus(422);
    }

    public function test_the_year_listing_reports_the_decision_and_the_count(): void
    {
        $holiday = $this->holiday();
        $hr = $this->user('hr-admin');

        $this->actingAs($hr, 'sanctum')
            ->postJson("/api/holiday-pay/{$holiday->id}/decide", ['status' => 'approved'])->assertOk();

        $this->actingAs($hr, 'sanctum')
            ->getJson('/api/holiday-pay?year='.now()->year)
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Independence Day')
            ->assertJsonPath('data.0.decisions.0.status', 'approved')
            ->assertJsonPath('data.0.worked_count', 0)
            ->assertJsonPath('can_decide', true);
    }

    /** An account manager may record who worked, but sees no decide button. */
    public function test_an_account_manager_is_told_they_cannot_decide(): void
    {
        $this->holiday();

        $this->actingAs($this->user('account-manager'), 'sanctum')
            ->getJson('/api/holiday-pay')
            ->assertOk()
            ->assertJsonPath('can_decide', false);
    }

    // ── Onboarding ───────────────────────────────────────────────────────

    public function test_an_employee_sees_their_own_onboarding_list(): void
    {
        $user = $this->user('employee');
        $employee = $this->employeeFor($user);

        OnboardingTask::create(['employee_id' => $employee->id, 'task' => 'Sign the contract', 'sort_order' => 1]);
        OnboardingTask::create(['employee_id' => $employee->id, 'task' => 'Collect ID badge', 'sort_order' => 2]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/onboarding')
            ->assertOk()
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.done', 0)
            ->assertJsonPath('data.0.task', 'Sign the contract');
    }

    /**
     * Passing somebody else's employee_id must not open their list. It falls back
     * to the caller's own rather than refusing, because the common case is the app
     * asking about the signed-in person.
     */
    public function test_an_employee_cannot_read_a_colleagues_list_by_passing_an_id(): void
    {
        $mine = $this->user('employee');
        $myEmployee = $this->employeeFor($mine);
        OnboardingTask::create(['employee_id' => $myEmployee->id, 'task' => 'Mine', 'sort_order' => 1]);

        $theirs = $this->user('employee');
        $theirEmployee = $this->employeeFor($theirs);
        OnboardingTask::create(['employee_id' => $theirEmployee->id, 'task' => 'Theirs', 'sort_order' => 1]);

        $this->actingAs($mine, 'sanctum')
            ->getJson('/api/onboarding?employee_id='.$theirEmployee->id)
            ->assertOk()
            ->assertJsonPath('data.0.task', 'Mine');
    }

    public function test_a_task_can_be_ticked_and_unticked(): void
    {
        $user = $this->user('employee');
        $employee = $this->employeeFor($user);
        $task = OnboardingTask::create(['employee_id' => $employee->id, 'task' => 'Sign the contract', 'sort_order' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/onboarding/{$task->id}/complete", ['completed' => true])
            ->assertOk();

        $this->assertNotNull($task->refresh()->completed_at);

        // Unticking matters: a task ticked by mistake should not need a desk.
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/onboarding/{$task->id}/complete", ['completed' => false])
            ->assertOk();

        $this->assertNull($task->refresh()->completed_at);
    }

    public function test_somebody_elses_task_cannot_be_ticked(): void
    {
        $owner = $this->user('employee');
        $employee = $this->employeeFor($owner);
        $task = OnboardingTask::create(['employee_id' => $employee->id, 'task' => 'Sign', 'sort_order' => 1]);

        $stranger = $this->user('employee');
        $this->employeeFor($stranger);

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/onboarding/{$task->id}/complete")
            ->assertForbidden();

        $this->assertNull($task->refresh()->completed_at);
    }

    // ── PIPs ─────────────────────────────────────────────────────────────

    public function test_an_employee_sees_only_their_own_pip(): void
    {
        $mine = $this->user('employee');
        $myEmployee = $this->employeeFor($mine);

        $theirs = $this->user('employee');
        $theirEmployee = $this->employeeFor($theirs);

        Pip::create(['employee_id' => $myEmployee->id, 'title' => 'Mine', 'status' => 'active', 'start_date' => '2026-09-01', 'end_date' => '2026-11-30']);
        Pip::create(['employee_id' => $theirEmployee->id, 'title' => 'Theirs', 'status' => 'active', 'start_date' => '2026-09-01', 'end_date' => '2026-11-30']);

        $this->actingAs($mine, 'sanctum')
            ->getJson('/api/pips')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Mine');
    }

    public function test_hr_sees_every_pip(): void
    {
        $a = $this->user('employee');
        $b = $this->user('employee');
        Pip::create(['employee_id' => $this->employeeFor($a)->id, 'title' => 'One', 'status' => 'active', 'start_date' => '2026-09-01', 'end_date' => '2026-11-30']);
        Pip::create(['employee_id' => $this->employeeFor($b)->id, 'title' => 'Two', 'status' => 'active', 'start_date' => '2026-09-01', 'end_date' => '2026-11-30']);

        $this->actingAs($this->user('hr-admin'), 'sanctum')
            ->getJson('/api/pips')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /** Objectives are stored as JSON and may be strings or objects. */
    public function test_objectives_are_flattened_to_text(): void
    {
        $user = $this->user('employee');
        $employee = $this->employeeFor($user);

        Pip::create([
            'employee_id' => $employee->id,
            'title' => 'Improve call handling',
            'status' => 'active',
            'start_date' => '2026-09-01',
            'end_date' => '2026-11-30',
            'objectives' => ['Answer within 3 rings', ['objective' => 'Log every call']],
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/pips')
            ->assertOk()
            ->assertJsonPath('data.0.objectives.0', 'Answer within 3 rings')
            ->assertJsonPath('data.0.objectives.1', 'Log every call');
    }
}
