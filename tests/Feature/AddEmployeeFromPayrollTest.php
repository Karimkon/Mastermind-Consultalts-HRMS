<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\PayrollManualDays;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Adding an employee who is missing, from the payroll selection screen.
 *
 * An account manager preparing a run finds somebody who was never loaded — a
 * new hire, or one the spreadsheet missed — and had to abandon a half-finished
 * selection to go and bulk-import or ask HR.
 *
 * The records written are the same ones the bulk import writes, so there is one
 * kind of employee in the system and not two. The salary record is the part
 * worth guarding: without it the engine calculates nothing and the person turns
 * up on the run with a payslip of zero, which is the whole problem this screen
 * exists to remove.
 */
class AddEmployeeFromPayrollTest extends TestCase
{
    use RefreshDatabase;

    private function accountManager(string $email): User
    {
        Role::findOrCreate('account-manager', 'web');
        Role::findOrCreate('employee', 'web');

        $u = User::create(['name' => 'Account Manager', 'email' => $email, 'password' => bcrypt('secret')]);
        $u->assignRole(['employee', 'account-manager']);

        return $u->fresh();
    }

    private function client(User $manager, string $name = 'UNOC (Uganda National Oil Company)'): Client
    {
        $portal = User::create([
            'name' => $name . ' portal',
            'email' => uniqid('portal') . '@test.local',
            'password' => bcrypt('secret'),
        ]);

        return Client::create([
            'user_id' => $portal->id,
            'company_name' => $name,
            'contact_person' => 'Contact',
            'status' => 'active',
            'account_manager_id' => $manager->id,
        ]);
    }

    private function payrollRun(Client $client): PayrollRun
    {
        return PayrollRun::create([
            'title' => 'October 2026',
            'month' => 10,
            'year' => 2026,
            'status' => 'draft',
            'client_id' => $client->id,
        ]);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'first_name' => 'Alex',
            'last_name' => 'Mudebo',
            'employment_type' => 'casual',
            'salary_type' => 'daily',
            'rate' => 16615,
            'hire_date' => '2026-10-01',
        ], $override);
    }

    // ===== What it creates =====

    public function test_the_employee_lands_in_employee_central_and_on_the_client(): void
    {
        $am = $this->accountManager('am@test.local');
        $client = $this->client($am);
        $run = $this->payrollRun($client);

        $this->actingAs($am)
            ->post("/account-manager/payroll/{$run->id}/employees", $this->payload())
            ->assertRedirect(route('account-manager.payroll.select', $run));

        $employee = Employee::where('first_name', 'Alex')->first();

        $this->assertNotNull($employee, 'No employee was created.');
        $this->assertSame('active', $employee->status);
        // Employee Central is the employees table, so being here IS being there.
        $this->assertTrue($client->employees()->where('employees.id', $employee->id)->exists(),
            'The employee was not assigned to the client.');
    }

    /** Without a salary the engine calculates nothing and pays zero. */
    public function test_a_salary_record_is_created(): void
    {
        $am = $this->accountManager('am2@test.local');
        $client = $this->client($am);
        $run = $this->payrollRun($client);

        $this->actingAs($am)->post("/account-manager/payroll/{$run->id}/employees",
            $this->payload(['rate' => 20000, 'salary_type' => 'daily']));

        $employee = Employee::where('first_name', 'Alex')->first();
        $salary = EmployeeSalary::where('employee_id', $employee->id)->where('is_current', true)->first();

        $this->assertNotNull($salary, 'No salary was recorded, so payroll would pay them nothing.');
        $this->assertSame(20000.0, (float) $salary->basic_salary);
        $this->assertSame('daily', $salary->salary_type);
    }

    public function test_a_rate_is_required(): void
    {
        $am = $this->accountManager('am3@test.local');
        $client = $this->client($am);
        $run = $this->payrollRun($client);

        $this->actingAs($am)
            ->post("/account-manager/payroll/{$run->id}/employees", $this->payload(['rate' => null]))
            ->assertSessionHasErrors('rate');

        $this->assertSame(0, Employee::count());
    }

    /** The number follows the client, as the bulk import does. */
    public function test_the_employee_number_is_prefixed_from_the_client(): void
    {
        $am = $this->accountManager('am4@test.local');
        $client = $this->client($am);
        $run = $this->payrollRun($client);

        $this->actingAs($am)->post("/account-manager/payroll/{$run->id}/employees", $this->payload());

        $this->assertStringStartsWith('UNO', Employee::first()->emp_number);
    }

    public function test_two_added_employees_do_not_collide(): void
    {
        $am = $this->accountManager('am5@test.local');
        $client = $this->client($am);
        $run = $this->payrollRun($client);

        $this->actingAs($am)->post("/account-manager/payroll/{$run->id}/employees", $this->payload());
        $this->actingAs($am)->post("/account-manager/payroll/{$run->id}/employees",
            $this->payload(['first_name' => 'Brenda', 'last_name' => 'Nantege']));

        $numbers = Employee::pluck('emp_number');
        $this->assertCount(2, $numbers);
        $this->assertSame(2, $numbers->unique()->count(), 'Two employees were given the same number.');
    }

    // ===== Putting them on the run =====

    public function test_days_given_put_them_straight_on_the_run(): void
    {
        $am = $this->accountManager('am6@test.local');
        $client = $this->client($am);
        $run = $this->payrollRun($client);

        $this->actingAs($am)->post("/account-manager/payroll/{$run->id}/employees",
            $this->payload(['days_worked' => 14]));

        $employee = Employee::first();
        $row = PayrollManualDays::where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)->first();

        $this->assertNotNull($row, 'Days were given but the employee was not put on the run.');
        $this->assertSame(14, (int) $row->days_worked);
    }

    public function test_without_days_they_are_added_but_not_on_the_run(): void
    {
        $am = $this->accountManager('am7@test.local');
        $client = $this->client($am);
        $run = $this->payrollRun($client);

        $this->actingAs($am)->post("/account-manager/payroll/{$run->id}/employees", $this->payload());

        $this->assertSame(1, Employee::count());
        $this->assertSame(0, PayrollManualDays::where('payroll_run_id', $run->id)->count());
    }

    // ===== Who may do it =====

    public function test_an_account_manager_cannot_add_to_a_client_they_do_not_manage(): void
    {
        $owner = $this->accountManager('owner@test.local');
        $other = $this->accountManager('other@test.local');
        $client = $this->client($owner);
        $run = $this->payrollRun($client);

        $this->actingAs($other)
            ->post("/account-manager/payroll/{$run->id}/employees", $this->payload())
            ->assertForbidden();

        $this->assertSame(0, Employee::count());
    }

    public function test_a_run_already_with_hr_cannot_take_new_employees(): void
    {
        $am = $this->accountManager('am8@test.local');
        $client = $this->client($am);
        $run = $this->payrollRun($client);
        $run->update(['status' => 'processed']);

        $this->actingAs($am)->post("/account-manager/payroll/{$run->id}/employees", $this->payload());

        $this->assertSame(0, Employee::count(), 'An employee was added to a run that has left the desk.');
    }

    // ===== The optional login =====

    public function test_an_email_gives_them_a_login(): void
    {
        $am = $this->accountManager('am9@test.local');
        $client = $this->client($am);
        $run = $this->payrollRun($client);

        $this->actingAs($am)->post("/account-manager/payroll/{$run->id}/employees",
            $this->payload(['email' => 'alex.mudebo@test.local']));

        $employee = Employee::where('first_name', 'Alex')->first();

        $this->assertNotNull($employee->user, 'No login was created for the supplied email.');
        $this->assertSame('alex.mudebo@test.local', $employee->user->email);
        $this->assertTrue($employee->user->hasRole('employee'));
    }

    public function test_an_email_already_in_use_is_refused(): void
    {
        $am = $this->accountManager('am10@test.local');
        $client = $this->client($am);
        $run = $this->payrollRun($client);

        $this->actingAs($am)
            ->post("/account-manager/payroll/{$run->id}/employees",
                $this->payload(['email' => 'am10@test.local']))   // the manager's own
            ->assertSessionHasErrors('email');

        $this->assertSame(0, Employee::count());
    }
}
