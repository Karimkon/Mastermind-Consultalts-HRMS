<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Designation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Adding a department or job title without leaving the employee.
 *
 * 661 of 1,247 live employees have no designation, whole clients at a time,
 * and the only way to add a missing one was Administration > Departments:
 * leave the employee, create it, come back, find them again, lose whatever
 * was typed. Both store endpoints existed already and answered with a
 * redirect, so nothing could call them from another page.
 *
 * The permission boundary is the part worth testing. Editing an employee is
 * open to super-admin, hr-admin, manager and payroll-officer; creating a
 * department is open to the first two only. A manager gets the form without
 * the button, and the endpoint refuses them even if they find it.
 */
class QuickAddPlacementTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(string $role): User
    {
        foreach (['super-admin', 'hr-admin', 'manager', 'payroll-officer', 'employee'] as $r) {
            Role::findOrCreate($r, 'web');
        }

        $u = User::create([
            'name' => ucfirst($role), 'email' => uniqid('u') . '@test.local', 'password' => bcrypt('secret'),
        ]);
        $u->assignRole(['employee', $role]);

        return $u->fresh();
    }

    // ===== creating one =====

    public function test_hr_can_add_a_designation_and_gets_it_back_as_json(): void
    {
        $hr = $this->userWith('hr-admin');

        $this->actingAs($hr)
            ->postJson('/admin/designations', ['title' => 'Detergent Packer'])
            ->assertCreated()
            ->assertJson(['title' => 'Detergent Packer', 'created' => true])
            ->assertJsonStructure(['id', 'title', 'created']);

        $this->assertDatabaseHas('designations', ['title' => 'Detergent Packer']);
    }

    public function test_hr_can_add_a_department_and_gets_it_back_as_json(): void
    {
        $hr = $this->userWith('hr-admin');

        $this->actingAs($hr)
            ->postJson('/admin/departments', ['name' => 'Detergent'])
            ->assertCreated()
            ->assertJson(['name' => 'Detergent', 'created' => true]);

        $this->assertDatabaseHas('departments', ['name' => 'Detergent']);
    }

    public function test_adding_one_that_exists_returns_it_rather_than_duplicating(): void
    {
        $hr = $this->userWith('hr-admin');
        $existing = Designation::create(['title' => 'Machine Operator']);

        $this->actingAs($hr)
            ->postJson('/admin/designations', ['title' => 'Machine Operator'])
            ->assertOk()
            ->assertJson(['id' => $existing->id, 'created' => false]);

        // Two people adding the same title minutes apart must not leave the
        // dropdown with two of it.
        $this->assertSame(1, Designation::where('title', 'Machine Operator')->count());
    }

    public function test_a_blank_name_is_refused(): void
    {
        $hr = $this->userWith('hr-admin');

        $this->actingAs($hr)
            ->postJson('/admin/designations', ['title' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');
    }

    // ===== who may =====

    public function test_a_manager_who_may_edit_an_employee_may_not_create_a_designation(): void
    {
        $manager = $this->userWith('manager');

        $this->actingAs($manager)
            ->postJson('/admin/designations', ['title' => 'Invented Title'])
            ->assertForbidden();

        $this->assertDatabaseMissing('designations', ['title' => 'Invented Title']);
    }

    public function test_a_payroll_officer_may_not_create_a_department(): void
    {
        $po = $this->userWith('payroll-officer');

        $this->actingAs($po)
            ->postJson('/admin/departments', ['name' => 'Invented Dept'])
            ->assertForbidden();
    }

    // ===== the existing screen is unchanged =====

    public function test_a_form_post_still_redirects_as_the_departments_screen_expects(): void
    {
        $hr = $this->userWith('hr-admin');

        // Not postJson: no Accept: application/json, so the old behaviour stands.
        $this->actingAs($hr)
            ->post('/admin/departments', ['name' => 'Logistics'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('departments', ['name' => 'Logistics']);
    }

    // ===== and the button is only offered to those who may use it =====

    public function test_the_employee_form_offers_the_button_to_hr(): void
    {
        $hr = $this->userWith('hr-admin');
        $employee = \App\Models\Employee::create([
            'emp_number' => 'QA-1', 'first_name' => 'Jumah', 'last_name' => 'Kayondo',
            'hire_date' => '2020-01-01', 'status' => 'active',
        ]);
        Department::create(['name' => 'Detergent']);

        $this->actingAs($hr)->get("/employees/{$employee->id}/edit")->assertOk()
            ->assertSee("openQuickAdd('designation')", false)
            ->assertSee("openQuickAdd('department')", false);
    }

    public function test_the_employee_form_does_not_offer_it_to_a_manager(): void
    {
        $manager = $this->userWith('manager');
        $employee = \App\Models\Employee::create([
            'emp_number' => 'QA-2', 'first_name' => 'Dalausi', 'last_name' => 'Kille',
            'hire_date' => '2020-01-01', 'status' => 'active',
        ]);

        // Offering a button that can only ever return 403 is worse than not
        // offering one.
        $this->actingAs($manager)->get("/employees/{$employee->id}/edit")->assertOk()
            ->assertDontSee("openQuickAdd('designation')", false);
    }
}
