<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Who may read and change an employee record.
 *
 * `show` and `update` had no check of any kind. Any signed-in user could fetch
 * any employee by id — 906 people's contact details, employment terms and status
 * — and PUT changes to them. On a system where one client's account manager
 * should not see another client's staff, and where `status` decides whether
 * somebody is on the payroll at all, that is not a small gap.
 */
class EmployeeAccessTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function employee(?User $user = null): Employee
    {
        static $n = 0;
        $n++;

        return Employee::create([
            'user_id' => $user?->id,
            'emp_number' => 'EMP-'.$n,
            'first_name' => 'Staff',
            'last_name' => 'Member '.$n,
            'hire_date' => '2025-01-01',
            'status' => 'active',
        ]);
    }

    private function clientFor(User $accountManager, Employee ...$staff): Client
    {
        $client = Client::create([
            // A client row owns a portal login of its own, separate from the
            // account manager who looks after it.
            'user_id' => User::factory()->create()->id,
            'company_name' => 'Roofings Uganda Limited',
            'contact_person' => 'Brenda Kansiime',
            'account_manager_id' => $accountManager->id,
            'status' => 'active',
        ]);

        // The pivot records who made the assignment, so it cannot be attached
        // bare.
        $client->employees()->attach(
            collect($staff)->pluck('id')->all(),
            ['assigned_by' => $accountManager->id],
        );

        return $client;
    }

    // ── Reading ──────────────────────────────────────────────────────────

    public function test_an_employee_cannot_read_a_colleagues_record(): void
    {
        $me = $this->user('employee');
        $this->employee($me);

        $colleague = $this->employee();

        $this->actingAs($me, 'sanctum')
            ->getJson("/api/employees/{$colleague->id}")
            ->assertForbidden();
    }

    public function test_an_employee_can_read_their_own_record(): void
    {
        $me = $this->user('employee');
        $mine = $this->employee($me);

        $this->actingAs($me, 'sanctum')
            ->getJson("/api/employees/{$mine->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $mine->id);
    }

    public function test_hr_can_read_anybody(): void
    {
        $someone = $this->employee();

        $this->actingAs($this->user('hr-admin'), 'sanctum')
            ->getJson("/api/employees/{$someone->id}")
            ->assertOk();
    }

    /** The rule this module turns on: an AM sees their own clients' staff. */
    public function test_an_account_manager_can_read_staff_on_a_client_they_manage(): void
    {
        $am = $this->user('account-manager');
        $staff = $this->employee();
        $this->clientFor($am, $staff);

        $this->actingAs($am, 'sanctum')
            ->getJson("/api/employees/{$staff->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $staff->id);
    }

    public function test_an_account_manager_cannot_read_another_managers_client(): void
    {
        $mine = $this->user('account-manager');
        $theirs = $this->user('account-manager');

        $myStaff = $this->employee();
        $theirStaff = $this->employee();

        $this->clientFor($mine, $myStaff);
        $this->clientFor($theirs, $theirStaff);

        $this->actingAs($mine, 'sanctum')
            ->getJson("/api/employees/{$theirStaff->id}")
            ->assertForbidden();
    }

    // ── Writing ──────────────────────────────────────────────────────────

    /**
     * Reading your own record is reasonable. Rewriting it is not — `status`
     * alone decides whether somebody is on the payroll.
     */
    public function test_an_employee_cannot_edit_their_own_record(): void
    {
        $me = $this->user('employee');
        $mine = $this->employee($me);

        $this->actingAs($me, 'sanctum')
            ->putJson("/api/employees/{$mine->id}", [
                'first_name' => 'Renamed',
                'last_name' => 'Myself',
                'status' => 'active',
            ])
            ->assertForbidden();

        $this->assertSame('Staff', $mine->refresh()->first_name);
    }

    public function test_an_account_manager_can_edit_their_own_clients_staff(): void
    {
        $am = $this->user('account-manager');
        $staff = $this->employee();
        $this->clientFor($am, $staff);

        $this->actingAs($am, 'sanctum')
            ->putJson("/api/employees/{$staff->id}", [
                'first_name' => 'Sande',
                'last_name' => 'Alamanzani',
                'phone' => '0700000000',
            ])
            ->assertOk();

        $this->assertSame('Sande', $staff->refresh()->first_name);
    }

    public function test_an_account_manager_cannot_edit_another_managers_client(): void
    {
        $mine = $this->user('account-manager');
        $theirs = $this->user('account-manager');

        $theirStaff = $this->employee();
        $this->clientFor($mine, $this->employee());
        $this->clientFor($theirs, $theirStaff);

        $this->actingAs($mine, 'sanctum')
            ->putJson("/api/employees/{$theirStaff->id}", [
                'first_name' => 'Hijacked',
                'last_name' => 'Record',
            ])
            ->assertForbidden();

        $this->assertSame('Staff', $theirStaff->refresh()->first_name);
    }
}
