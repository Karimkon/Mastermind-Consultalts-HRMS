<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The Login / Payroll Email field on Employee Central.
 *
 * It failed silently in two ways. If the employee had no login there was no row
 * to write the address to, and hundreds of staff imported from payroll sheets
 * have none. If the address already belonged to somebody it was dropped without
 * a word. Either way the page said "updated successfully" and the field came
 * back holding the old value — indistinguishable from a broken field.
 */
class EmployeeEmailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super-admin', 'employee'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super-admin');
    }

    private function staff(string $number, ?User $user = null): Employee
    {
        return Employee::create([
            'user_id'    => $user?->id,
            'emp_number' => $number,
            'first_name' => 'Akol',
            'last_name'  => 'Deograceous',
            'hire_date'  => '2024-01-15',
            'status'     => 'active',
        ]);
    }

    private function save(Employee $e, array $fields = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->put(route('employees.update', $e), array_merge([
            'first_name' => $e->first_name,
            'last_name'  => $e->last_name,
        ], $fields));
    }

    public function test_an_address_is_saved_for_somebody_who_already_has_a_login(): void
    {
        $user = User::factory()->create(['email' => 'old@mc.ug']);
        $employee = $this->staff('HQ002', $user);

        $this->save($employee, ['login_email' => 'hq2.h@mc.ug'])->assertRedirect();

        $this->assertSame('hq2.h@mc.ug', $user->refresh()->email);
    }

    /**
     * Most staff imported from a payroll sheet have no user account, so there
     * was nothing for the address to be written to.
     */
    public function test_an_address_creates_a_login_when_there_is_none(): void
    {
        $employee = $this->staff('UNO001');

        $this->assertNull($employee->user_id);

        $this->save($employee, ['login_email' => 'justine.nabaloga@mc.ug'])->assertRedirect();

        $employee->refresh();

        $this->assertNotNull($employee->user_id, 'No login was created, so the address had nowhere to go.');
        $this->assertSame('justine.nabaloga@mc.ug', $employee->user->email);
        $this->assertSame('Akol Deograceous', $employee->user->name);
        $this->assertTrue($employee->user->hasRole('employee'));
    }

    /** A refusal has to be visible, or it is indistinguishable from a bug. */
    public function test_an_address_already_in_use_is_reported_not_dropped(): void
    {
        User::factory()->create(['name' => 'Ian Kirabo', 'email' => 'taken@mc.ug']);
        $employee = $this->staff('HQ002');

        $this->save($employee, ['login_email' => 'taken@mc.ug'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull($employee->refresh()->user_id);
    }

    public function test_the_message_names_who_holds_the_address(): void
    {
        User::factory()->create(['name' => 'Ian Kirabo', 'email' => 'taken@mc.ug']);
        $employee = $this->staff('HQ002');

        $this->save($employee, ['login_email' => 'taken@mc.ug']);

        $this->assertStringContainsString('Ian Kirabo', session('error'));
    }

    /** Saving an unchanged address must not report a clash with itself. */
    public function test_keeping_the_same_address_is_not_a_clash(): void
    {
        $user = User::factory()->create(['email' => 'hq2.h@mc.ug']);
        $employee = $this->staff('HQ002', $user);

        $this->save($employee, ['login_email' => 'hq2.h@mc.ug'])
            ->assertRedirect()
            ->assertSessionMissing('error');

        $this->assertSame('hq2.h@mc.ug', $user->refresh()->email);
    }

    // ── Searching by it ──────────────────────────────────────────────────

    public function test_staff_can_be_found_by_email(): void
    {
        $user = User::factory()->create(['name' => 'Akol Deograceous', 'email' => 'hq2.h@mc.ug']);
        $this->staff('HQ002', $user);

        $this->actingAs($this->admin)
            ->get(route('employees.index', ['search' => 'hq2.h@mc.ug']))
            ->assertOk()
            ->assertSee('HQ002');
    }

    /** Staff with no login are found by their own name, not the user's. */
    public function test_staff_without_a_login_can_still_be_found_by_name(): void
    {
        $this->staff('UNO001');

        $this->actingAs($this->admin)
            ->get(route('employees.index', ['search' => 'Deograceous']))
            ->assertOk()
            ->assertSee('UNO001');
    }

    public function test_a_full_name_in_either_order_finds_them(): void
    {
        $this->staff('UNO001');

        foreach (['Akol Deograceous', 'Deograceous Akol'] as $term) {
            $this->actingAs($this->admin)
                ->get(route('employees.index', ['search' => $term]))
                ->assertOk()
                ->assertSee('UNO001');
        }
    }
}
