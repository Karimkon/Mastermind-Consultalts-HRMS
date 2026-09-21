<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Finding people by where they are placed.
 *
 * Placement is the site somebody works at — Industrial Area, Lubowa, Banda. On
 * a client of 439 staff it is the one thing an account manager sorts by, and
 * the list could only be filtered by name, id or status.
 */
class AccountManagerPlacementFilterTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;
    private User $am;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('account-manager', 'web');

        $this->am = User::factory()->create();
        $this->am->assignRole('account-manager');

        $this->client = Client::create([
            'company_name' => 'Roofings Uganda Limited',
            'contact_person' => 'Someone',
            'account_manager_id' => $this->am->id,
            // clients.user_id is NOT NULL: a client row carries its own portal login.
            'user_id' => User::factory()->create()->id,
        ]);
    }

    private function staff(string $number, string $first, ?Department $placement): Employee
    {
        $employee = Employee::create([
            'emp_number' => $number,
            'first_name' => $first,
            'last_name' => 'Worker',
            'hire_date' => '2024-01-15',
            'status' => 'active',
            'department_id' => $placement?->id,
        ]);

        // assigned_by is NOT NULL on the pivot - the assignment records who made it.
        $this->client->employees()->attach($employee->id, ['assigned_by' => $this->am->id]);

        return $employee;
    }

    public function test_the_list_can_be_narrowed_to_one_placement(): void
    {
        $industrial = Department::create(['name' => 'Industrial Area']);
        $lubowa = Department::create(['name' => 'Lubowa']);

        $this->staff('RUL001', 'Sande', $industrial);
        $this->staff('RUL179', 'Otim', $lubowa);

        $this->actingAs($this->am)
            ->get(route('account-manager.employees', ['placement' => $industrial->id]))
            ->assertOk()
            ->assertSee('Sande')
            ->assertDontSee('Otim');
    }

    public function test_without_the_filter_everybody_is_listed(): void
    {
        $industrial = Department::create(['name' => 'Industrial Area']);
        $lubowa = Department::create(['name' => 'Lubowa']);

        $this->staff('RUL001', 'Sande', $industrial);
        $this->staff('RUL179', 'Otim', $lubowa);

        $this->actingAs($this->am)
            ->get(route('account-manager.employees'))
            ->assertOk()
            ->assertSee('Sande')
            ->assertSee('Otim');
    }

    public function test_the_dropdown_offers_the_placements_in_use(): void
    {
        $industrial = Department::create(['name' => 'Industrial Area']);
        $lubowa = Department::create(['name' => 'Lubowa']);

        $this->staff('RUL001', 'Sande', $industrial);
        $this->staff('RUL179', 'Otim', $lubowa);

        $this->actingAs($this->am)
            ->get(route('account-manager.employees'))
            ->assertOk()
            ->assertSee('All Placements')
            ->assertSee('Industrial Area')
            ->assertSee('Lubowa');
    }

    /**
     * Offering all 48 departments would mostly be sites this account manager
     * does not cover, and every one of those choices returns nothing.
     */
    public function test_a_placement_nobody_here_works_at_is_not_offered(): void
    {
        $industrial = Department::create(['name' => 'Industrial Area']);
        Department::create(['name' => 'Sheraton STEWARDING']);

        $this->staff('RUL001', 'Sande', $industrial);

        $this->actingAs($this->am)
            ->get(route('account-manager.employees'))
            ->assertOk()
            ->assertSee('Industrial Area')
            ->assertDontSee('Sheraton STEWARDING');
    }

    /** The filter must survive paging, or page 2 silently shows everybody. */
    public function test_the_filter_is_carried_across_pages(): void
    {
        $industrial = Department::create(['name' => 'Industrial Area']);
        $lubowa = Department::create(['name' => 'Lubowa']);

        for ($i = 1; $i <= 30; $i++) {
            $this->staff(sprintf('RUL%03d', $i), 'Industrial'.$i, $industrial);
        }
        $this->staff('RUL900', 'Otim', $lubowa);

        $html = $this->actingAs($this->am)
            ->get(route('account-manager.employees', ['placement' => $industrial->id]))
            ->assertOk()
            ->assertDontSee('Otim')
            ->getContent();

        $this->assertStringContainsString('placement='.$industrial->id, $html,
            'The pagination links dropped the placement filter.');
    }

    public function test_it_combines_with_the_name_search(): void
    {
        $industrial = Department::create(['name' => 'Industrial Area']);

        $this->staff('RUL001', 'Sande', $industrial);
        $this->staff('RUL002', 'Ashiraf', $industrial);

        $this->actingAs($this->am)
            ->get(route('account-manager.employees', [
                'placement' => $industrial->id,
                'search' => 'Ashiraf',
            ]))
            ->assertOk()
            ->assertSee('Ashiraf')
            ->assertDontSee('Sande');
    }

    /**
     * The list is bounded to this account manager's own people, so a placement
     * id belonging to somebody else's client returns nothing rather than
     * leaking a row.
     */
    public function test_another_managers_placement_shows_nothing(): void
    {
        $mine = Department::create(['name' => 'Industrial Area']);
        $theirs = Department::create(['name' => 'Somewhere Else']);

        $this->staff('RUL001', 'Sande', $mine);

        $other = Employee::create([
            'emp_number' => 'OTH001',
            'first_name' => 'Hidden',
            'last_name' => 'Person',
            'hire_date' => '2024-01-15',
            'status' => 'active',
            'department_id' => $theirs->id,
        ]);

        $this->actingAs($this->am)
            ->get(route('account-manager.employees', ['placement' => $theirs->id]))
            ->assertOk()
            ->assertDontSee('Hidden');
    }
}
