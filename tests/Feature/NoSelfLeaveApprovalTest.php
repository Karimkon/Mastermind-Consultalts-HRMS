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
 * Nobody decides their own leave.
 *
 * Every check asked what role you hold, never whose request it is — so HR, who
 * approves everybody's leave, approved their own too. Verified on the live
 * system: the HR Manager granted himself two days and it was recorded as an
 * ordinary approval, with his own name against it.
 *
 * There is no exemption, super-admin included. This is a separation of duties,
 * not a question of rank: the point is that a second person looks at it, and
 * the most senior account is where that matters most.
 */
class NoSelfLeaveApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRoles(string ...$roles): User
    {
        $u = User::factory()->create(['status' => 'active']);
        foreach ($roles as $r) {
            Role::findOrCreate($r, 'web');
            $u->assignRole($r);
        }

        return $u;
    }

    private function employeeFor(User $user, string $first = 'Ian'): Employee
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

    private function type(): LeaveType
    {
        return LeaveType::firstOrCreate(
            ['code' => 'ANNUAL'],
            ['name' => 'Annual Leave', 'days_allowed' => 21]
        );
    }

    private function request(Employee $e): LeaveRequest
    {
        return LeaveRequest::create([
            'employee_id'   => $e->id,
            'leave_type_id' => $this->type()->id,
            'from_date'     => now()->addDays(3),
            'to_date'       => now()->addDays(4),
            'days_count'    => 2,
            'status'        => 'pending',
            'reason'        => 'Personal',
        ]);
    }

    public function test_hr_cannot_approve_their_own_leave(): void
    {
        $hr    = $this->userWithRoles('hr-admin');
        $leave = $this->request($this->employeeFor($hr));

        $this->actingAs($hr)->post("/leaves/{$leave->id}/approve")->assertForbidden();

        $this->assertSame('pending', $leave->fresh()->status);
    }

    public function test_hr_cannot_reject_their_own_either(): void
    {
        $hr    = $this->userWithRoles('hr-admin');
        $leave = $this->request($this->employeeFor($hr));

        $this->actingAs($hr)
            ->post("/leaves/{$leave->id}/reject", ['rejection_reason' => 'Changed my mind.'])
            ->assertForbidden();

        $this->assertSame('pending', $leave->fresh()->status);
    }

    public function test_not_even_a_super_admin(): void
    {
        // Rank is not the point. A second pair of eyes is.
        $admin = $this->userWithRoles('super-admin');
        $leave = $this->request($this->employeeFor($admin, 'Admin'));

        $this->actingAs($admin)->post("/leaves/{$leave->id}/approve")->assertForbidden();

        $this->assertSame('pending', $leave->fresh()->status);
    }

    public function test_hr_still_approves_everybody_elses(): void
    {
        $hr = $this->userWithRoles('hr-admin');
        $this->employeeFor($hr);

        $staff = $this->userWithRoles('employee');
        $leave = $this->request($this->employeeFor($staff, 'Sande'));

        $this->actingAs($hr)->post("/leaves/{$leave->id}/approve")->assertRedirect();

        $this->assertSame('approved', $leave->fresh()->status);
    }

    public function test_another_hr_can_action_the_first_ones_leave(): void
    {
        $ian   = $this->userWithRoles('hr-admin');
        $leave = $this->request($this->employeeFor($ian));

        // Somebody has to be able to, or HR could never take leave at all.
        $colleague = $this->userWithRoles('hr-admin');
        $this->employeeFor($colleague, 'Colleague');

        $this->actingAs($colleague)->post("/leaves/{$leave->id}/approve")->assertRedirect();

        $this->assertSame('approved', $leave->fresh()->status);
    }

    public function test_the_api_refuses_it_too(): void
    {
        $hr    = $this->userWithRoles('hr-admin');
        $leave = $this->request($this->employeeFor($hr));

        $this->actingAs($hr, 'sanctum')
            ->postJson("/api/leaves/{$leave->id}/approve")
            ->assertForbidden();

        $this->assertSame('pending', $leave->fresh()->status);
    }

    public function test_the_buttons_are_not_offered_on_your_own_request(): void
    {
        $hr = $this->userWithRoles('hr-admin');

        // The card is behind @can("leave.approve"), so without the permission
        // this would pass for the wrong reason — there would be no buttons to
        // withhold.
        $hr->givePermissionTo(
            \Spatie\Permission\Models\Permission::findOrCreate('leave.approve', 'web')
        );

        $leave = $this->request($this->employeeFor($hr));

        // Saying so beats hiding the card, and beats a 403 after the click.
        $this->actingAs($hr)->get("/leaves/{$leave->id}")
            ->assertOk()
            ->assertSee('This one is yours')
            ->assertDontSee('Approve Leave');
    }

    public function test_a_user_with_no_employee_record_is_unaffected(): void
    {
        $hr = $this->userWithRoles('hr-admin');   // no employee row at all
        $staff = $this->userWithRoles('employee');
        $leave = $this->request($this->employeeFor($staff, 'Sande'));

        $this->assertFalse($leave->isOwnRequestOf($hr));
        $this->actingAs($hr)->post("/leaves/{$leave->id}/approve")->assertRedirect();
    }
}
