<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Who can see a leave request, and what the balance beside it says.
 *
 * Two things were wrong. The list showed the days requested and nothing about
 * the entitlement they come out of, so approving meant opening every row. And
 * show() had no authorisation at all — any signed-in user could read anybody's
 * leave request, reason included, by changing the number in the URL.
 */
class LeaveBalanceVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    private function employeeFor(User $user, string $first, ?int $managerId = null): Employee
    {
        return Employee::create([
            'user_id'    => $user->id,
            'emp_number' => 'MM' . $user->id,
            'first_name' => $first,
            'last_name'  => 'Test',
            'manager_id' => $managerId,
            'hire_date'  => now()->subYear(),
            'status'     => 'active',
        ]);
    }

    private function leaveType(int $allowed = 21): LeaveType
    {
        return LeaveType::create([
            'name'         => 'Annual Leave',
            'code'         => 'ANNUAL',
            'days_allowed' => $allowed,
        ]);
    }

    private function request(Employee $e, LeaveType $t, string $status = 'pending'): LeaveRequest
    {
        return LeaveRequest::create([
            'employee_id'   => $e->id,
            'leave_type_id' => $t->id,
            'from_date'     => now()->addDays(3),
            'to_date'       => now()->addDays(5),
            'days_count'    => 3,
            'status'        => $status,
            'reason'        => 'Sorting some personal issues',
        ]);
    }

    // ── The reporting line ──────────────────────────────────────────────────

    public function test_a_manager_sees_their_whole_branch_not_just_direct_reports(): void
    {
        $bossUser = $this->userWithRole('employee');
        $boss     = $this->employeeFor($bossUser, 'Boss');

        $midUser  = $this->userWithRole('employee');
        $mid      = $this->employeeFor($midUser, 'Middle', $boss->id);

        $juniorU  = $this->userWithRole('employee');
        $junior   = $this->employeeFor($juniorU, 'Junior', $mid->id);

        // Two rungs down, and the boss is still accountable for the absence.
        $this->assertEqualsCanonicalizing([$mid->id, $junior->id], $boss->descendantIds());

        $type = $this->leaveType();
        $this->request($junior, $type);

        $rows = $this->actingAs($bossUser)->get('/leaves')->assertOk()->viewData('requests');
        $this->assertCount(1, $rows);
    }

    public function test_somebody_outside_the_line_sees_nothing_of_it(): void
    {
        $type     = $this->leaveType();
        $stranger = $this->userWithRole('employee');
        $this->employeeFor($stranger, 'Stranger');

        $otherU = $this->userWithRole('employee');
        $this->request($this->employeeFor($otherU, 'Other'), $type);

        $this->assertCount(0, $this->actingAs($stranger)->get('/leaves')->assertOk()->viewData('requests'));
    }

    public function test_a_reporting_line_that_loops_does_not_hang(): void
    {
        // Hand-entered data, so this is possible: A reports to B, B reports to A.
        $aU = $this->userWithRole('employee');
        $a  = $this->employeeFor($aU, 'A');
        $bU = $this->userWithRole('employee');
        $b  = $this->employeeFor($bU, 'B', $a->id);
        $a->update(['manager_id' => $b->id]);

        $this->assertSame([$b->id], $a->fresh()->descendantIds());
    }

    // ── Opening one ─────────────────────────────────────────────────────────

    public function test_you_cannot_read_a_stranger_s_leave_request(): void
    {
        $type   = $this->leaveType();
        $ownerU = $this->userWithRole('employee');
        $leave  = $this->request($this->employeeFor($ownerU, 'Owner'), $type);

        $stranger = $this->userWithRole('employee');
        $this->employeeFor($stranger, 'Stranger');

        $this->actingAs($stranger)->get("/leaves/{$leave->id}")->assertForbidden();
        $this->actingAs($ownerU)->get("/leaves/{$leave->id}")->assertOk();
    }

    public function test_a_manager_and_hr_can_open_it(): void
    {
        $type    = $this->leaveType();
        $bossU   = $this->userWithRole('employee');
        $boss    = $this->employeeFor($bossU, 'Boss');
        $staffU  = $this->userWithRole('employee');
        $leave   = $this->request($this->employeeFor($staffU, 'Staff', $boss->id), $type);

        $this->actingAs($bossU)->get("/leaves/{$leave->id}")->assertOk();
        $this->actingAs($this->userWithRole('hr-admin'))->get("/leaves/{$leave->id}")->assertOk();
    }

    // ── The balance beside the request ──────────────────────────────────────

    public function test_the_list_carries_taken_pending_and_remaining(): void
    {
        $type  = $this->leaveType(21);
        $userU = $this->userWithRole('employee');
        $emp   = $this->employeeFor($userU, 'Sande');

        LeaveBalance::create([
            'employee_id'   => $emp->id,
            'leave_type_id' => $type->id,
            'year'          => now()->year,
            'total_days'    => 21,
            'used_days'     => 4,
            'pending_days'  => 3,
        ]);
        $this->request($emp, $type);

        $balances = $this->actingAs($userU)->get('/leaves')->assertOk()->viewData('balances');
        $key      = $emp->id . ':' . $type->id;

        $this->assertSame(21.0, $balances[$key]['entitled']);
        $this->assertSame(4.0,  $balances[$key]['used']);
        $this->assertSame(3.0,  $balances[$key]['pending']);
        $this->assertSame(14.0, $balances[$key]['remaining']);
        $this->assertTrue($balances[$key]['seeded']);
    }

    public function test_with_no_balance_row_it_falls_back_to_the_type_allowance(): void
    {
        $type  = $this->leaveType(21);
        $userU = $this->userWithRole('employee');
        $emp   = $this->employeeFor($userU, 'Sande');
        $this->request($emp, $type);

        $balances = $this->actingAs($userU)->get('/leaves')->assertOk()->viewData('balances');
        $row      = $balances[$emp->id . ':' . $type->id];

        $this->assertSame(21.0, $row['entitled']);
        $this->assertSame(21.0, $row['remaining']);
        // Flagged, so nobody reads a default as a granted entitlement.
        $this->assertFalse($row['seeded']);
    }

    public function test_the_balances_cost_the_same_whatever_the_page_holds(): void
    {
        $type = $this->leaveType();
        $hr   = $this->userWithRole('hr-admin');

        for ($i = 0; $i < 12; $i++) {
            $u = $this->userWithRole('employee');
            $this->request($this->employeeFor($u, "Staff{$i}"), $type);
        }

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->actingAs($hr)->get('/leaves')->assertOk();
        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        // Two for the whole page — one for the balance rows, one for the type
        // allowances. Calling balanceFor() per row would have been two each.
        $balanceQueries = collect($queries)->filter(
            fn($q) => str_contains($q['query'], 'leave_balances') || str_contains($q['query'], 'from "leave_types"')
        );
        $this->assertLessThanOrEqual(4, $balanceQueries->count(),
            'The balance lookup must not grow with the number of rows.');
    }
}
