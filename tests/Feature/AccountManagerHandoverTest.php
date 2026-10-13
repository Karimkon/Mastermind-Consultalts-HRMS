<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Appointing, handing over and standing down an account manager.
 *
 * None of it could be done from the system. "Add Account Manager" led to the
 * user-create form, so appointing somebody who already had a login meant giving
 * them a second one — which is how one person ended up holding the head-office
 * client under an account nobody signs into. There was no way to remove the
 * role at all, and no way to move a book of business except one client at a
 * time.
 *
 * The thing worth stating about transfers: employees are never named. An
 * account manager reaches staff through their clients, so moving a client moves
 * its people with it. A separate control for "and their employees" could only
 * ever disagree with the client assignment, so the tests here assert that the
 * staff follow rather than that a second switch was thrown.
 */
class AccountManagerHandoverTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super-admin', 'account-manager', 'employee'] as $r) {
            Role::findOrCreate($r, 'web');
        }

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('super-admin');
    }

    private function manager(string $name): User
    {
        $u = User::factory()->create(['status' => 'active', 'name' => $name]);
        $u->assignRole('account-manager');

        return $u;
    }

    private function clientFor(User $am, string $name, int $staff = 0): Client
    {
        $client = Client::create([
            'company_name' => $name,
            'contact_person' => 'Someone',
            'account_manager_id' => $am->id,
            'user_id' => User::factory()->create()->id,
        ]);

        for ($i = 0; $i < $staff; $i++) {
            $e = Employee::create([
                'emp_number' => 'E'.fake()->unique()->numberBetween(10000, 99999),
                'first_name' => 'Staff'.$i,
                'last_name' => 'Member',
                'hire_date' => '2024-01-15',
                'status' => 'active',
            ]);
            $client->employees()->attach($e->id, ['assigned_by' => $am->id]);
        }

        return $client;
    }

    // ──────────────────────────── the page itself ────────────────────────────

    /**
     * Every control these tests POST to has to exist on a page somebody can
     * reach. A suite made of POSTs alone passes happily while the screen that
     * submits them is a 500, because nothing ever renders it.
     */
    public function test_the_account_managers_page_renders_with_the_controls_on_it(): void
    {
        $am = $this->manager('Violet Namuleme');
        $this->clientFor($am, 'Roofings Uganda Limited', 2);
        $this->manager('Omar Shamilah');

        $this->actingAs($this->admin)
            ->get(route('admin.account-managers.index'))
            ->assertOk()
            ->assertSee('Violet Namuleme')
            ->assertSee('Roofings Uganda Limited')
            ->assertSee('Appoint')          // promote
            ->assertSee('Hand over')        // transfer
            ->assertSee('Stand down');      // remove
    }

    public function test_an_ordinary_employee_cannot_reach_that_page(): void
    {
        $staff = User::factory()->create(['status' => 'active']);
        $staff->assignRole('employee');

        $this->actingAs($staff)
            ->get(route('admin.account-managers.index'))
            ->assertForbidden();
    }

    // ───────────────────────────── appointing ─────────────────────────────

    public function test_an_existing_user_can_be_appointed(): void
    {
        // Rather than creating them a second login, which is the habit that
        // produced the duplicate accounts in the first place.
        $staff = User::factory()->create(['status' => 'active']);
        $staff->assignRole('employee');

        $this->actingAs($this->admin)
            ->post(route('admin.account-managers.promote'), ['user_id' => $staff->id])
            ->assertRedirect();

        $this->assertTrue($staff->fresh()->hasRole('account-manager'));
    }

    public function test_appointing_does_not_disturb_the_roles_they_already_hold(): void
    {
        $staff = User::factory()->create(['status' => 'active']);
        $staff->assignRole('employee');

        $this->actingAs($this->admin)
            ->post(route('admin.account-managers.promote'), ['user_id' => $staff->id]);

        $this->assertEqualsCanonicalizing(
            ['employee', 'account-manager'],
            $staff->fresh()->roles->pluck('name')->all(),
        );
    }

    // ────────────────────────────── handing over ──────────────────────────────

    public function test_a_whole_book_moves_in_one_step(): void
    {
        $from = $this->manager('Violet');
        $to = $this->manager('Shamilah');

        $this->clientFor($from, 'Roofings Uganda Limited', 3);
        $this->clientFor($from, 'UNOC', 2);

        $this->actingAs($this->admin)
            ->post(route('admin.account-managers.transfer'), [
                'from_user_id' => $from->id,
                'to_user_id' => $to->id,
            ])
            ->assertRedirect();

        $this->assertSame(0, Client::where('account_manager_id', $from->id)->count());
        $this->assertSame(2, Client::where('account_manager_id', $to->id)->count());
    }

    public function test_the_employees_go_with_their_client(): void
    {
        // The point of the whole feature. Nothing moves the employees directly;
        // they follow because the client did.
        $from = $this->manager('Violet');
        $to = $this->manager('Shamilah');

        $client = $this->clientFor($from, 'Roofings Uganda Limited', 4);

        $this->actingAs($this->admin)
            ->post(route('admin.account-managers.transfer'), [
                'from_user_id' => $from->id,
                'to_user_id' => $to->id,
            ]);

        $reached = $to->fresh()->managedClients
            ->flatMap(fn ($c) => $c->employees->pluck('id'))
            ->unique();

        $this->assertCount(4, $reached);
        $this->assertCount(0, $from->fresh()->managedClients);
    }

    public function test_clients_cannot_be_handed_to_somebody_who_is_not_an_account_manager(): void
    {
        $from = $this->manager('Violet');
        $outsider = User::factory()->create(['status' => 'active']);
        $outsider->assignRole('employee');

        $this->clientFor($from, 'Roofings Uganda Limited', 2);

        $this->actingAs($this->admin)
            ->post(route('admin.account-managers.transfer'), [
                'from_user_id' => $from->id,
                'to_user_id' => $outsider->id,
            ])
            ->assertStatus(422);

        $this->assertSame(1, Client::where('account_manager_id', $from->id)->count());
    }

    // ───────────────────────────── standing down ─────────────────────────────

    public function test_somebody_holding_nothing_can_be_stood_down(): void
    {
        $am = $this->manager('Spare');

        $this->actingAs($this->admin)
            ->post(route('admin.account-managers.remove'), ['user_id' => $am->id])
            ->assertRedirect();

        $this->assertFalse($am->fresh()->hasRole('account-manager'));
    }

    public function test_somebody_still_holding_clients_cannot_be_stood_down_silently(): void
    {
        /*
         | Revoking the role on its own would leave the client pointing at
         | somebody who can no longer see it: the staff stay on the books with
         | nobody able to run their payroll or action their leave, and nothing
         | on screen says why.
         */
        $am = $this->manager('Violet');
        $this->clientFor($am, 'Roofings Uganda Limited', 3);

        $this->actingAs($this->admin)
            ->post(route('admin.account-managers.remove'), ['user_id' => $am->id])
            ->assertRedirect();

        $this->assertTrue($am->fresh()->hasRole('account-manager'), 'still an account manager');
        $this->assertSame(1, Client::where('account_manager_id', $am->id)->count());
    }

    public function test_but_can_be_stood_down_when_somebody_takes_the_clients(): void
    {
        $am = $this->manager('Violet');
        $successor = $this->manager('Shamilah');
        $this->clientFor($am, 'Roofings Uganda Limited', 3);

        $this->actingAs($this->admin)
            ->post(route('admin.account-managers.remove'), [
                'user_id' => $am->id,
                'transfer_to' => $successor->id,
            ])
            ->assertRedirect();

        $this->assertFalse($am->fresh()->hasRole('account-manager'));
        $this->assertSame(1, Client::where('account_manager_id', $successor->id)->count());
    }

    public function test_standing_down_keeps_the_login_and_the_history(): void
    {
        // People keep their account. Payroll runs they processed and staff they
        // assigned still point at them, and deleting the row would orphan it.
        $am = $this->manager('Violet');

        $this->actingAs($this->admin)
            ->post(route('admin.account-managers.remove'), ['user_id' => $am->id]);

        $this->assertDatabaseHas('users', ['id' => $am->id, 'status' => 'active']);
    }
}
