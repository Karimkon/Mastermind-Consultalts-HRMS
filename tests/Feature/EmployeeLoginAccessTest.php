<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Setting an employee's login password from Employee Central.
 *
 * There was no way to do this at all. A login created alongside an employee got
 * a random 32-character password, and the only route back in was the
 * forgot-password email — which is no use to client-site staff who have no
 * working mailbox. HR could not hand somebody their credentials, and could not
 * sign in as a test account to check a screen.
 */
class EmployeeLoginAccessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'super-admin'): User
    {
        Role::findOrCreate($role, 'web');
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    private function employee(?User $user = null): Employee
    {
        return Employee::create([
            'user_id'        => $user?->id,
            'emp_number'     => 'MM' . fake()->unique()->numberBetween(1000, 9999),
            'first_name'     => 'Ashiraf',
            'last_name'      => 'Lule',
            'personal_email' => 'ashiraf' . fake()->unique()->numberBetween(1, 9999) . '@example.com',
            'hire_date'      => now()->subYear(),
            'status'         => 'active',
        ]);
    }

    public function test_an_admin_sets_a_password_the_employee_can_sign_in_with(): void
    {
        Role::findOrCreate('employee', 'web');
        $staff = User::factory()->create(['email' => 'ashiraf.lule@roofings.com']);
        $staff->assignRole('employee');
        $employee = $this->employee($staff);

        $this->actingAs($this->admin())
            ->post("/employees/{$employee->id}/reset-password", [
                'password'              => 'NewPass@2026',
                'password_confirmation' => 'NewPass@2026',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('NewPass@2026', $staff->refresh()->password));

        // The point of the whole feature: the credentials actually work.
        $this->assertTrue(auth()->attempt([
            'email' => 'ashiraf.lule@roofings.com', 'password' => 'NewPass@2026',
        ]));
    }

    public function test_it_creates_a_login_for_an_employee_who_has_none(): void
    {
        Role::findOrCreate('employee', 'web');
        $employee = $this->employee();
        $this->assertNull($employee->user_id);

        $this->actingAs($this->admin())
            ->post("/employees/{$employee->id}/reset-password", [
                'email'                 => 'newstarter@example.com',
                'password'              => 'FirstPass@1',
                'password_confirmation' => 'FirstPass@1',
            ])
            ->assertRedirect();

        $employee->refresh();
        $this->assertNotNull($employee->user_id);
        $this->assertSame('newstarter@example.com', $employee->user->email);
        $this->assertTrue($employee->user->hasRole('employee'));
    }

    public function test_hr_may_do_it_too(): void
    {
        Role::findOrCreate('employee', 'web');
        $staff    = User::factory()->create();
        $employee = $this->employee($staff);

        $this->actingAs($this->admin('hr-admin'))
            ->post("/employees/{$employee->id}/reset-password", [
                'password' => 'HrSet@2026', 'password_confirmation' => 'HrSet@2026',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('HrSet@2026', $staff->refresh()->password));
    }

    public function test_nobody_else_may(): void
    {
        $staff    = User::factory()->create();
        $original = $staff->password;
        $employee = $this->employee($staff);

        foreach (['account-manager', 'payroll-officer', 'employee', 'md'] as $role) {
            $this->actingAs($this->admin($role))
                ->post("/employees/{$employee->id}/reset-password", [
                    'password' => 'Sneaky@2026', 'password_confirmation' => 'Sneaky@2026',
                ])
                ->assertForbidden("{$role} must not be able to set somebody's password.");
        }

        $this->assertSame($original, $staff->refresh()->password);
    }

    public function test_the_two_passwords_have_to_match(): void
    {
        $staff    = User::factory()->create();
        $original = $staff->password;
        $employee = $this->employee($staff);

        $this->actingAs($this->admin())
            ->post("/employees/{$employee->id}/reset-password", [
                'password' => 'NewPass@2026', 'password_confirmation' => 'Different@2026',
            ])
            ->assertSessionHasErrors('password');

        $this->assertSame($original, $staff->refresh()->password);
    }

    public function test_a_short_password_is_refused(): void
    {
        $employee = $this->employee(User::factory()->create());

        $this->actingAs($this->admin())
            ->post("/employees/{$employee->id}/reset-password", [
                'password' => 'short', 'password_confirmation' => 'short',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_an_email_already_used_by_somebody_else_is_refused(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $staff    = User::factory()->create();
        $employee = $this->employee($staff);

        // Otherwise this hands the employee somebody else's account.
        $this->actingAs($this->admin())
            ->post("/employees/{$employee->id}/reset-password", [
                'email'    => 'taken@example.com',
                'password' => 'NewPass@2026', 'password_confirmation' => 'NewPass@2026',
            ])
            ->assertSessionHas('error');

        $this->assertNotSame('taken@example.com', $staff->refresh()->email);
    }
}
