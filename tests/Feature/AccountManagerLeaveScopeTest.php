<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Whose leave an account manager may decide.
 *
 * An account manager approves leave for the people they place with clients.
 * That is the job. Two things are not the job, and both were reachable:
 *
 *   - **Mastermind's own staff.** Head-office employees are assigned to the HQ
 *     client exactly like any client placement, because that is how payroll and
 *     the staff register find them. The account manager holding that client
 *     could therefore approve leave for all 31 of their own colleagues. Leave
 *     for Mastermind staff belongs to HR, the CEO or the MD. ("CEO" is the md
 *     role — see QualityDocumentController, which says the same.)
 *
 *   - **Their own leave.** Three of the six account managers are themselves
 *     assigned to the HQ client. An account manager who held that client could
 *     approve their own request, and the module's only check asked which
 *     employees they manage, never whose request it was.
 *
 * Both are asserted against the POST and not the page. A filtered screen hides
 * the button; it does not stop the request, and the request is what changes the
 * database.
 */
class AccountManagerLeaveScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $am;

    private Client $clientSite;

    private Client $headOffice;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['account-manager', 'hr-admin', 'md', 'super-admin'] as $r) {
            Role::findOrCreate($r, 'web');
        }

        $this->am = User::factory()->create(['status' => 'active']);
        $this->am->assignRole('account-manager');

        // An ordinary client: the account manager's actual patch.
        $this->clientSite = Client::create([
            'company_name' => 'Roofings Uganda Limited',
            'contact_person' => 'Someone',
            'account_manager_id' => $this->am->id,
            'user_id' => User::factory()->create()->id,
            'is_head_office' => false,
        ]);

        // Mastermind itself. Held by the same account manager, which is the
        // situation in production: one account manager holds the HQ client.
        $this->headOffice = Client::create([
            'company_name' => 'Mastermind Consult Ltd HQ',
            'contact_person' => 'Someone',
            'account_manager_id' => $this->am->id,
            'user_id' => User::factory()->create()->id,
            'is_head_office' => true,
        ]);
    }

    private function staff(Client $client, string $first, ?User $user = null): Employee
    {
        $employee = Employee::create([
            'user_id' => $user?->id,
            'emp_number' => 'E'.fake()->unique()->numberBetween(1000, 9999),
            'first_name' => $first,
            'last_name' => 'Worker',
            'hire_date' => '2024-01-15',
            'status' => 'active',
        ]);

        // assigned_by is NOT NULL on the pivot.
        $client->employees()->attach($employee->id, ['assigned_by' => $this->am->id]);

        return $employee;
    }

    private function leaveFor(Employee $e): LeaveRequest
    {
        return LeaveRequest::create([
            'employee_id' => $e->id,
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

    private function userWithRole(string $role): User
    {
        $u = User::factory()->create(['status' => 'active']);
        $u->assignRole($role);

        return $u;
    }

    // ───────────────────────────── the job ─────────────────────────────

    public function test_an_account_manager_approves_leave_for_the_people_they_place(): void
    {
        $leave = $this->leaveFor($this->staff($this->clientSite, 'Sande'));

        $this->actingAs($this->am)
            ->post(route('account-manager.leaves.approve', $leave->id))
            ->assertRedirect();

        $this->assertSame('approved', $leave->fresh()->status);
    }

    public function test_and_sees_them_on_the_page(): void
    {
        $this->leaveFor($this->staff($this->clientSite, 'Sande'));

        $this->actingAs($this->am)
            ->get(route('account-manager.leaves'))
            ->assertOk()
            ->assertSee('Sande');
    }

    // ────────────────────── not Mastermind's own staff ──────────────────────

    public function test_head_office_staff_are_not_listed(): void
    {
        $this->leaveFor($this->staff($this->headOffice, 'Deograceous'));

        $this->actingAs($this->am)
            ->get(route('account-manager.leaves'))
            ->assertOk()
            ->assertDontSee('Deograceous');
    }

    public function test_head_office_leave_cannot_be_approved_even_by_posting_directly(): void
    {
        // The one that matters. Hiding a row does not stop the request.
        $leave = $this->leaveFor($this->staff($this->headOffice, 'Deograceous'));

        $this->actingAs($this->am)
            ->post(route('account-manager.leaves.approve', $leave->id))
            ->assertForbidden();

        $this->assertSame('pending', $leave->fresh()->status);
    }

    public function test_nor_rejected(): void
    {
        $leave = $this->leaveFor($this->staff($this->headOffice, 'Deograceous'));

        $this->actingAs($this->am)
            ->post(route('account-manager.leaves.reject', $leave->id), ['reason' => 'No'])
            ->assertForbidden();

        $this->assertSame('pending', $leave->fresh()->status);
    }

    // ──────────────────────────── nor their own ────────────────────────────

    public function test_an_account_manager_cannot_approve_their_own_leave(): void
    {
        // Assigned to a client they hold, which is exactly how three of the
        // real account managers sit.
        $own = $this->staff($this->clientSite, 'Shamilah', $this->am);
        $leave = $this->leaveFor($own);

        $this->actingAs($this->am)
            ->post(route('account-manager.leaves.approve', $leave->id))
            ->assertForbidden();

        $this->assertSame('pending', $leave->fresh()->status);
    }

    public function test_their_own_request_is_not_even_listed(): void
    {
        $this->leaveFor($this->staff($this->clientSite, 'Shamilah', $this->am));

        $this->actingAs($this->am)
            ->get(route('account-manager.leaves'))
            ->assertOk()
            ->assertDontSee('Shamilah');
    }

    // ───────────────────── who it goes to instead ─────────────────────

    /**
     * HR, the CEO and the MD. "CEO" is the md role: one person holds it and he
     * is the CEO, as QualityDocumentController records.
     */
    public static function approvers(): array
    {
        return [
            'HR' => ['hr-admin'],
            'CEO / MD' => ['md'],
            'System Admin' => ['super-admin'],
        ];
    }

    /** @dataProvider approvers */
    public function test_head_office_leave_is_theirs_to_approve(string $role): void
    {
        $leave = $this->leaveFor($this->staff($this->headOffice, 'Deograceous'));

        $this->actingAs($this->userWithRole($role))
            ->post("/leaves/{$leave->id}/approve")
            ->assertRedirect();

        $this->assertSame('approved', $leave->fresh()->status);
    }

    public function test_an_account_manager_may_still_apply_for_their_own_leave(): void
    {
        // The rule is about who DECIDES, not about who may ask. An account
        // manager is a Mastermind employee and takes leave like anybody else;
        // it simply goes to HR, the CEO or the MD.
        $this->staff($this->headOffice, 'Shamilah', $this->am);

        $this->actingAs($this->am)
            ->get(route('leaves.create'))
            ->assertOk();
    }
}
