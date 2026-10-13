<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A user with no employee record must see no leave, not all of it.
 *
 * index() scoped the list with `->when(!$isAdmin && $employee, ...)`. For
 * anybody without an employee record that condition is false, so when() applied
 * no filter at all and the query returned every leave request in the company.
 * The guard written to narrow the list switched the narrowing off.
 *
 * 16 live accounts are in that position, and 13 of them are client portal
 * logins — ONOMO, Sheraton, Serena, UNOC, Bidco, Marriott and others. A
 * customer could read Mastermind's internal leave, including leave for staff
 * placed at a competitor of theirs. Two more are account managers who were
 * never given an employee record.
 *
 * Confirmed before the fix: a client-role login got 200 on /leaves with
 * somebody else's request on the page.
 */
class LeaveVisibilityFailsClosedTest extends TestCase
{
    use RefreshDatabase;

    private function somebodyElsesLeave(): LeaveRequest
    {
        $staff = Employee::create([
            'emp_number' => 'E900',
            'first_name' => 'Confidential',
            'last_name' => 'Person',
            'hire_date' => now()->subYear(),
            'status' => 'active',
        ]);

        return LeaveRequest::create([
            'employee_id' => $staff->id,
            'leave_type_id' => LeaveType::firstOrCreate(
                ['code' => 'ANNUAL'],
                ['name' => 'Annual Leave', 'days_allowed' => 21]
            )->id,
            'from_date' => now()->addDays(3),
            'to_date' => now()->addDays(4),
            'days_count' => 2,
            'status' => 'pending',
            'reason' => 'Private matter',
        ]);
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $u = User::factory()->create(['status' => 'active']);
        $u->assignRole($role);

        return $u;
    }

    public static function rolesWithNoEmployeeRecord(): array
    {
        return [
            'a client portal login' => ['client'],
            'an account manager with no staff record' => ['account-manager'],
            'an employee role with no staff record' => ['employee'],
        ];
    }

    /** @dataProvider rolesWithNoEmployeeRecord */
    public function test_sees_none_of_it(string $role): void
    {
        $this->somebodyElsesLeave();

        $this->actingAs($this->userWithRole($role))
            ->get('/leaves')
            ->assertOk()
            ->assertDontSee('Confidential');
    }

    public function test_somebody_with_a_staff_record_still_sees_their_own(): void
    {
        // The fix must not blank the page for the people it is actually for.
        $this->somebodyElsesLeave();

        $user = $this->userWithRole('employee');
        $mine = Employee::create([
            'user_id' => $user->id,
            'emp_number' => 'E901',
            'first_name' => 'Ordinary',
            'last_name' => 'Staff',
            'hire_date' => now()->subYear(),
            'status' => 'active',
        ]);

        LeaveRequest::create([
            'employee_id' => $mine->id,
            'leave_type_id' => LeaveType::where('code', 'ANNUAL')->value('id'),
            'from_date' => now()->addDays(5),
            'to_date' => now()->addDays(6),
            'days_count' => 2,
            'status' => 'pending',
            'reason' => 'Mine',
        ]);

        $this->actingAs($user)
            ->get('/leaves')
            ->assertOk()
            ->assertSee('Ordinary')
            ->assertDontSee('Confidential');
    }

    public function test_hr_still_sees_everything(): void
    {
        $this->somebodyElsesLeave();

        $this->actingAs($this->userWithRole('hr-admin'))
            ->get('/leaves')
            ->assertOk()
            ->assertSee('Confidential');
    }
}
