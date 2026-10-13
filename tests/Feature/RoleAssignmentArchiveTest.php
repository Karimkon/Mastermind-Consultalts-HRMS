<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\RoleAssignments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Who has held which post, and when.
 *
 * model_has_roles records only who holds a role right now — three columns, no
 * dates — and removing a role deletes the row. The audit log cannot fill the
 * gap either: Spatie writes straight to the pivot rather than through a model,
 * so of 4,084 audit rows not one mentions a role. "Who was HR before Ian" was
 * unanswerable.
 *
 * The archive is written beside the permission change, never instead of it.
 * The last group here is the point of that: account managers must reach exactly
 * the same data as before, because access is still decided by the pivot.
 */
class RoleAssignmentArchiveTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private RoleAssignments $roles;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super-admin', 'hr-admin', 'account-manager', 'employee', 'payroll-officer'] as $r) {
            Role::findOrCreate($r, 'web');
        }

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('super-admin');
        $this->actingAs($this->admin);

        $this->roles = app(RoleAssignments::class);
    }

    private function person(string $name): array
    {
        $u = User::factory()->create(['status' => 'active', 'name' => $name]);
        $e = Employee::create([
            'user_id' => $u->id,
            'emp_number' => 'E'.$u->id,
            'first_name' => explode(' ', $name)[0],
            'last_name' => explode(' ', $name)[1] ?? 'Staff',
            'hire_date' => now()->subYear(),
            'status' => 'active',
        ]);

        return [$u, $e];
    }

    // ─────────────────────────────── the archive ───────────────────────────────

    public function test_a_departed_hr_is_still_on_the_record(): void
    {
        // The question that could not be answered before.
        [$ian] = $this->person('Ian Kirabo');
        [$grace] = $this->person('Grace Nakato');

        $this->roles->grant($ian, 'hr-admin', 'Appointed 2024');
        $this->roles->revoke($ian, 'hr-admin', 'Left the company');
        $this->roles->grant($grace, 'hr-admin', 'Taking over from Ian');

        $everyHr = $this->roles->holdersOf('hr-admin');

        $this->assertCount(2, $everyHr, 'both the current and the former HR');
        $this->assertEqualsCanonicalizing(
            ['Ian Kirabo', 'Grace Nakato'],
            $everyHr->map(fn ($a) => $a->user->name)->all(),
        );
    }

    public function test_the_current_holder_is_distinguishable_from_the_former_one(): void
    {
        [$ian] = $this->person('Ian Kirabo');
        [$grace] = $this->person('Grace Nakato');

        $this->roles->grant($ian, 'hr-admin');
        $this->roles->revoke($ian, 'hr-admin');
        $this->roles->grant($grace, 'hr-admin');

        $current = $this->roles->holdersOf('hr-admin')->whereNull('ended_at');

        $this->assertCount(1, $current);
        $this->assertSame('Grace Nakato', $current->first()->user->name);
    }

    public function test_revoking_removes_the_access_but_keeps_the_record(): void
    {
        // Both halves matter. Keeping the record would be pointless if the
        // person could still act; removing the access would be pointless if the
        // record went with it.
        [$ian] = $this->person('Ian Kirabo');

        $this->roles->grant($ian, 'hr-admin');
        $this->roles->revoke($ian, 'hr-admin');

        $this->assertFalse($ian->fresh()->hasRole('hr-admin'), 'access is gone');
        $this->assertSame(1, RoleAssignment::where('user_id', $ian->id)->count(), 'the record is not');
        $this->assertNotNull(RoleAssignment::where('user_id', $ian->id)->first()->ended_at);
    }

    public function test_somebody_can_hold_a_post_twice(): void
    {
        // Two separate spells, not one row edited twice.
        [$ian] = $this->person('Ian Kirabo');

        $this->roles->grant($ian, 'hr-admin');
        $this->roles->revoke($ian, 'hr-admin');
        $this->roles->grant($ian, 'hr-admin');

        $this->assertSame(2, RoleAssignment::where('user_id', $ian->id)->where('role_name', 'hr-admin')->count());
        $this->assertTrue($ian->fresh()->hasRole('hr-admin'));
    }

    public function test_who_made_the_change_is_recorded(): void
    {
        [$ian] = $this->person('Ian Kirabo');

        $this->roles->grant($ian, 'hr-admin', 'Promoted');

        $a = RoleAssignment::where('user_id', $ian->id)->first();

        $this->assertSame($this->admin->id, $a->assigned_by);
        $this->assertSame('Promoted', $a->reason);
    }

    // ───────────────────────── from Employee Central ─────────────────────────

    public function test_a_role_can_be_assigned_from_the_employee_record(): void
    {
        [$u, $e] = $this->person('Grace Nakato');

        $this->post(route('employees.roles.assign', $e), [
            'role' => 'hr-admin',
            'reason' => 'Taking over from Ian',
        ])->assertRedirect();

        $this->assertTrue($u->fresh()->hasRole('hr-admin'));
        $this->assertSame('Taking over from Ian', RoleAssignment::where('user_id', $u->id)->first()->reason);
    }

    public function test_an_employee_with_no_login_is_told_why_not(): void
    {
        // 312 of 1,557 people are in this position, so it is the ordinary case
        // and not an edge one.
        $e = Employee::create([
            'emp_number' => 'E999',
            'first_name' => 'No',
            'last_name' => 'Login',
            'hire_date' => now()->subYear(),
            'status' => 'active',
        ]);

        $this->post(route('employees.roles.assign', $e), ['role' => 'hr-admin'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, RoleAssignment::count());
    }

    public function test_the_employee_page_shows_the_roles_tab(): void
    {
        [$u, $e] = $this->person('Ian Kirabo');
        $this->roles->grant($u, 'hr-admin');

        $this->get(route('employees.show', $e))
            ->assertOk()
            ->assertSee('Roles Held')
            ->assertSee('Hr admin');
    }

    // ───────────────── nothing that already worked may break ─────────────────

    public function test_an_account_manager_appointed_through_the_service_reaches_their_clients(): void
    {
        /*
         | The regression that matters. Access is still decided by Spatie's
         | pivot; the archive is written beside it. If recording ever replaced
         | assigning, an account manager would silently lose their whole book.
         */
        [$violet] = $this->person('Violet Namuleme');

        $this->roles->grant($violet, 'account-manager', 'Appointed');

        $client = Client::create([
            'company_name' => 'Roofings Uganda Limited',
            'contact_person' => 'Someone',
            'account_manager_id' => $violet->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $employee = Employee::create([
            'emp_number' => 'RUL001',
            'first_name' => 'Sande',
            'last_name' => 'Worker',
            'hire_date' => '2024-01-15',
            'status' => 'active',
        ]);
        $client->employees()->attach($employee->id, ['assigned_by' => $violet->id]);

        $this->assertTrue($violet->fresh()->hasRole('account-manager'));
        $this->assertCount(1, $violet->fresh()->managedClients);

        $this->actingAs($violet->fresh())
            ->get(route('account-manager.employees'))
            ->assertOk()
            ->assertSee('Sande');
    }

    public function test_standing_an_account_manager_down_removes_access_as_before(): void
    {
        [$violet] = $this->person('Violet Namuleme');
        $this->roles->grant($violet, 'account-manager');
        $this->roles->revoke($violet, 'account-manager', 'Stood down');

        $this->assertFalse($violet->fresh()->hasRole('account-manager'));

        $this->actingAs($violet->fresh())
            ->get(route('account-manager.employees'))
            ->assertForbidden();
    }

    public function test_syncing_roles_still_grants_and_revokes_exactly_as_before(): void
    {
        [$u] = $this->person('Many Hats');
        $u->syncRoles(['employee']);

        $this->roles->sync($u, ['employee', 'account-manager', 'payroll-officer']);

        $this->assertEqualsCanonicalizing(
            ['employee', 'account-manager', 'payroll-officer'],
            $u->fresh()->roles->pluck('name')->all(),
        );

        $this->roles->sync($u, ['employee']);

        $this->assertSame(['employee'], $u->fresh()->roles->pluck('name')->all());

        // And the two that ended are on the record, not erased.
        $this->assertSame(2, RoleAssignment::where('user_id', $u->id)->whereNotNull('ended_at')->count());
    }
}
