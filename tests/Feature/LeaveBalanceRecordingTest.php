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
 * Approving leave has to actually record the days.
 *
 * It did not. Every approval ran
 *
 *     LeaveBalance::where(...)->increment('used_days', $days);
 *
 * which matches no rows and silently changes nothing when that person has no
 * balance row for the year — and only 2 of 1,246 employees had one. So leave
 * was approved, the days vanished, and the screen went on showing the leave
 * type's default with nothing taken. Ian Kirabo's approved day read as 0 taken
 * out of 21 for exactly this reason.
 */
class LeaveBalanceRecordingTest extends TestCase
{
    use RefreshDatabase;

    private function hr(): User
    {
        Role::findOrCreate('hr-admin', 'web');
        $u = User::factory()->create();
        $u->assignRole('hr-admin');

        return $u;
    }

    private function employee(): Employee
    {
        Role::findOrCreate('employee', 'web');
        $u = User::factory()->create();
        $u->assignRole('employee');

        return Employee::create([
            'user_id'    => $u->id,
            'emp_number' => 'MM' . $u->id,
            'first_name' => 'Sande',
            'last_name'  => 'Test',
            'hire_date'  => now()->subYear(),
            'status'     => 'active',
        ]);
    }

    private function type(int $allowed = 21): LeaveType
    {
        return LeaveType::create(['name' => 'Annual Leave', 'code' => 'ANNUAL', 'days_allowed' => $allowed]);
    }

    private function request(Employee $e, LeaveType $t, float $days = 1): LeaveRequest
    {
        return LeaveRequest::create([
            'employee_id'   => $e->id,
            'leave_type_id' => $t->id,
            'from_date'     => now()->addDays(3),
            'to_date'       => now()->addDays(3 + (int) $days - 1),
            'days_count'    => $days,
            'status'        => 'pending',
            'reason'        => 'Personal',
        ]);
    }

    public function test_approving_records_the_days_even_with_no_balance_row(): void
    {
        $employee = $this->employee();
        $type     = $this->type(21);
        $leave    = $this->request($employee, $type, 1);

        // The state almost every employee is in: no row at all.
        $this->assertSame(0, LeaveBalance::where('employee_id', $employee->id)->count());

        $this->actingAs($this->hr())->post("/leaves/{$leave->id}/approve")->assertRedirect();

        $balance = LeaveBalance::where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)->first();

        $this->assertNotNull($balance, 'The row has to be created, not silently skipped.');
        $this->assertSame(1.0, (float) $balance->used_days);
        $this->assertSame(21.0, (float) $balance->total_days, 'Seeded from the leave type allowance.');
    }

    public function test_the_screen_then_agrees_with_what_was_approved(): void
    {
        $employee = $this->employee();
        $type     = $this->type(21);
        $leave    = $this->request($employee, $type, 1);

        $hr = $this->hr();
        $this->actingAs($hr)->post("/leaves/{$leave->id}/approve");

        $balance = $this->actingAs($hr)->get("/leaves/{$leave->id}")->assertOk()->viewData('balance');

        // 1 taken out of 21, not 0 — which is what was on screen.
        $this->assertSame(1.0, $balance['used']);
        $this->assertSame(20.0, $balance['remaining']);
    }

    public function test_an_existing_row_is_added_to_rather_than_replaced(): void
    {
        $employee = $this->employee();
        $type     = $this->type(21);

        LeaveBalance::create([
            'employee_id'   => $employee->id,
            'leave_type_id' => $type->id,
            'year'          => now()->year,
            'total_days'    => 21,
            'used_days'     => 4,
            'pending_days'  => 0,
        ]);

        $leave = $this->request($employee, $type, 2);
        $this->actingAs($this->hr())->post("/leaves/{$leave->id}/approve");

        // The balance holds every other leave this person has taken this year.
        $this->assertSame(6.0, (float) LeaveBalance::where('employee_id', $employee->id)->value('used_days'));
    }

    public function test_a_balance_never_reads_below_zero(): void
    {
        $employee = $this->employee();
        $type     = $this->type(21);
        $leave    = $this->request($employee, $type, 3);

        // More days coming back than ever went out — a figure like -2 is worse
        // than 0, because somebody will believe it.
        app(\App\Services\LeaveAdjustmentService::class)->moveBalance($leave, 'used_days', -5);

        $this->assertSame(0.0, (float) LeaveBalance::where('employee_id', $employee->id)->value('used_days'));
    }
}
