<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Deleting a client, and refusing to.
 *
 * There was no delete at all, which left merged duplicates like
 * "Roofings Outlets (merged)" sitting in the list forever. Adding one naively
 * would have been worse than leaving it out: seven foreign keys onto `clients`
 * are ON DELETE CASCADE, so deleting a trading client takes every employee
 * placement, salary payment, site visit and holiday pay approval recorded
 * against it. Roofings Uganda Limited would have taken 447 rows with it.
 *
 * payroll_runs is the quiet one. It carries client_id with no foreign key, so
 * it neither cascades nor blocks -- the rows would just be left pointing at a
 * client that no longer exists.
 */
class ClientDeletionTest extends TestCase
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
        $this->actingAs($this->admin);
    }

    private function client(string $name): Client
    {
        return Client::create([
            'company_name' => $name,
            'contact_person' => 'Someone',
            'user_id' => User::factory()->create()->id,
        ]);
    }

    private function employee(string $first = 'Some', string $last = 'Body'): Employee
    {
        return Employee::create([
            'emp_number' => 'E'.fake()->unique()->numberBetween(10000, 99999),
            'first_name' => $first,
            'last_name' => $last,
            'hire_date' => '2024-01-15',
            'status' => 'active',
        ]);
    }

    public function test_an_empty_client_can_be_deleted(): void
    {
        // The case the button exists for: a merged duplicate holding nobody.
        $client = $this->client('Roofings Outlets (merged)');

        $this->delete(route('admin.clients.destroy', $client))
            ->assertRedirect(route('admin.clients.index'))
            ->assertSessionHas('success');

        $this->assertNull(Client::find($client->id));
    }

    public function test_a_client_with_staff_placed_there_cannot_be_deleted(): void
    {
        /*
         | The one that matters. client_employee_assignments cascades, so
         | without this guard deleting the client silently erases the record of
         | everybody who was ever placed there.
         */
        $client = $this->client('Roofings Uganda Limited');
        $client->employees()->attach($this->employee()->id, ['assigned_by' => $this->admin->id]);

        $this->delete(route('admin.clients.destroy', $client))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNotNull(Client::find($client->id), 'the client survived');
        $this->assertSame(1, DB::table('client_employee_assignments')->where('client_id', $client->id)->count(),
            'and so did the placement');
    }

    public function test_the_refusal_says_what_is_holding_it(): void
    {
        $client = $this->client('Roofings Uganda Limited');
        $client->employees()->attach($this->employee()->id, ['assigned_by' => $this->admin->id]);
        $client->employees()->attach($this->employee('Second', 'Person')->id, ['assigned_by' => $this->admin->id]);

        $this->delete(route('admin.clients.destroy', $client));

        $this->assertStringContainsString('2 employee placements', session('error'));
        $this->assertStringContainsString('inactive', session('error'), 'it offers the alternative');
    }

    public function test_a_client_with_a_payroll_run_cannot_be_deleted(): void
    {
        // payroll_runs has no foreign key, so nothing in the database would
        // have stopped this or cleaned up after it.
        $client = $this->client('Serena Kampala Hotel');

        DB::table('payroll_runs')->insert([
            'client_id' => $client->id,
            'title' => 'September 2026',
            'month' => 9,
            'year' => 2026,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->delete(route('admin.clients.destroy', $client))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNotNull(Client::find($client->id));
        $this->assertSame(1, DB::table('payroll_runs')->where('client_id', $client->id)->count(),
            'the payroll run was not orphaned');
    }

    public function test_the_delete_button_is_offered_on_an_empty_client(): void
    {
        $client = $this->client('Roofings Outlets (merged)');

        $this->get(route('admin.clients.show', $client))
            ->assertOk()
            ->assertSee('Delete');
    }

    public function test_the_delete_button_is_not_offered_on_a_client_holding_staff(): void
    {
        $client = $this->client('Roofings Uganda Limited');
        $client->employees()->attach($this->employee()->id, ['assigned_by' => $this->admin->id]);

        $this->get(route('admin.clients.show', $client))
            ->assertOk()
            ->assertSee('records attached')
            ->assertDontSee('Delete permanently');
    }

    public function test_an_ordinary_employee_cannot_delete_a_client(): void
    {
        $client = $this->client('Roofings Outlets (merged)');

        $staff = User::factory()->create(['status' => 'active']);
        $staff->assignRole('employee');

        $this->actingAs($staff)
            ->delete(route('admin.clients.destroy', $client))
            ->assertForbidden();

        $this->assertNotNull(Client::find($client->id));
    }

    public function test_an_account_manager_cannot_delete_a_client(): void
    {
        $client = $this->client('Roofings Outlets (merged)');

        $am = User::factory()->create(['status' => 'active']);
        $am->assignRole('account-manager');

        $this->actingAs($am)
            ->delete(route('admin.clients.destroy', $client))
            ->assertForbidden();

        $this->assertNotNull(Client::find($client->id));
    }
}
