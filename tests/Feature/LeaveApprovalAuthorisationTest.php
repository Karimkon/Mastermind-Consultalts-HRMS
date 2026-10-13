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
 * Who may decide somebody else's leave.
 *
 * `approve`, `reject` and `cancel` had no authorisation check of any kind. The
 * only question they asked was "is this your own request" — added when HR was
 * found approving their own leave — and nobody went back to ask the prior
 * question of whether the caller should be deciding leave at all.
 *
 * So any of the 1,242 holders of the employee role could approve anybody's
 * leave by posting to the route. Confirmed before it was fixed: a plain
 * employee POSTed to /leaves/{id}/approve for a colleague and it was recorded
 * as an ordinary approval, balance movement and notification included.
 *
 * It survived because the page never offered the button. The form was gated
 * and the write was not, which is the only kind of hole a screen-driven test
 * cannot see — every assertion here is therefore a POST.
 */
class LeaveApprovalAuthorisationTest extends TestCase
{
    use RefreshDatabase;

    private Employee $applicant;

    private LeaveRequest $leave;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['employee', 'hr-admin', 'md', 'super-admin', 'account-manager'] as $r) {
            Role::findOrCreate($r, 'web');
        }

        $this->applicant = Employee::create([
            'emp_number' => 'E100',
            'first_name' => 'Some',
            'last_name' => 'Body',
            'hire_date' => now()->subYear(),
            'status' => 'active',
        ]);

        $this->leave = LeaveRequest::create([
            'employee_id' => $this->applicant->id,
            'leave_type_id' => LeaveType::firstOrCreate(
                ['code' => 'ANNUAL'],
                ['name' => 'Annual Leave', 'days_allowed' => 21]
            )->id,
            'from_date' => now()->addDays(3),
            'to_date' => now()->addDays(4),
            'days_count' => 2,
            'status' => 'pending',
            'reason' => 'Personal',
        ]);
    }

    private function userWithRole(string $role, bool $withEmployee = true): User
    {
        $u = User::factory()->create(['status' => 'active']);
        $u->assignRole($role);

        if ($withEmployee) {
            Employee::create([
                'user_id' => $u->id,
                'emp_number' => 'E'.$u->id,
                'first_name' => 'Staff',
                'last_name' => 'Member',
                'hire_date' => now()->subYear(),
                'status' => 'active',
            ]);
        }

        return $u;
    }

    // ───────────────────────────── the hole ─────────────────────────────

    public function test_a_colleague_cannot_approve_your_leave(): void
    {
        $bystander = $this->userWithRole('employee');

        $this->actingAs($bystander)
            ->post("/leaves/{$this->leave->id}/approve")
            ->assertForbidden();

        $this->assertSame('pending', $this->leave->fresh()->status);
    }

    public function test_nor_reject_it(): void
    {
        $bystander = $this->userWithRole('employee');

        $this->actingAs($bystander)
            ->post("/leaves/{$this->leave->id}/reject", ['reason' => 'No'])
            ->assertForbidden();

        $this->assertSame('pending', $this->leave->fresh()->status);
    }

    public function test_nor_cancel_it(): void
    {
        $bystander = $this->userWithRole('employee');

        $this->actingAs($bystander)
            ->post("/leaves/{$this->leave->id}/cancel")
            ->assertForbidden();

        $this->assertSame('pending', $this->leave->fresh()->status);
    }

    public function test_an_account_manager_cannot_reach_round_the_side(): void
    {
        // Their own module is scoped to the staff they place. The general route
        // must not be a way round that scope.
        $am = $this->userWithRole('account-manager');

        $this->actingAs($am)
            ->post("/leaves/{$this->leave->id}/approve")
            ->assertForbidden();

        $this->assertSame('pending', $this->leave->fresh()->status);
    }

    // ────────────────────────── who still can ──────────────────────────

    public static function approvers(): array
    {
        return [
            'HR' => ['hr-admin'],
            'CEO / MD' => ['md'],
            'System Admin' => ['super-admin'],
        ];
    }

    /** @dataProvider approvers */
    public function test_the_approving_offices_still_can(string $role): void
    {
        $this->actingAs($this->userWithRole($role))
            ->post("/leaves/{$this->leave->id}/approve")
            ->assertRedirect();

        $this->assertSame('approved', $this->leave->fresh()->status);
    }

    public function test_a_line_manager_can_decide_for_their_own_report(): void
    {
        /*
         | Kept on purpose. index() already shows a manager their whole branch,
         | because the person who has to cover the absence is the one who needs
         | to see it coming -- approving it is the other half of that, and
         | sending every request to HR would stall the queue.
         */
        $boss = $this->userWithRole('employee');
        $bossEmployee = $boss->employee;

        // manager_id, not supervisor_id: descendantIds() walks manager_id.
        $this->applicant->update(['manager_id' => $bossEmployee->id]);

        $this->actingAs($boss)
            ->post("/leaves/{$this->leave->id}/approve")
            ->assertRedirect();

        $this->assertSame('approved', $this->leave->fresh()->status);
    }

    public function test_but_not_for_somebody_outside_their_branch(): void
    {
        $boss = $this->userWithRole('employee');

        // No reporting line to the applicant at all.
        $this->actingAs($boss)
            ->post("/leaves/{$this->leave->id}/approve")
            ->assertForbidden();

        $this->assertSame('pending', $this->leave->fresh()->status);
    }

    // ─────────────────────────── your own ───────────────────────────

    public function test_you_may_still_withdraw_your_own_request(): void
    {
        // A retraction, not a decision. The applicant keeps this.
        $me = $this->userWithRole('employee');

        $mine = LeaveRequest::create([
            'employee_id' => $me->employee->id,
            'leave_type_id' => LeaveType::where('code', 'ANNUAL')->value('id'),
            'from_date' => now()->addDays(3),
            'to_date' => now()->addDays(4),
            'days_count' => 2,
            'status' => 'pending',
            'reason' => 'Changed my mind',
        ]);

        $this->actingAs($me)
            ->post("/leaves/{$mine->id}/cancel")
            ->assertRedirect();

        $this->assertSame('cancelled', $mine->fresh()->status);
    }
}
