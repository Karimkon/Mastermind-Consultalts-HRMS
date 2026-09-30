<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Who may open a payslip.
 *
 * The PDF route sits outside the payroll role group so that an employee can
 * fetch their own slip. It carried a comment saying the controller scoped by
 * employee_id. It did not: both the run id and the employee id came straight
 * from the URL and nothing checked them against the signed-in user, so any of
 * the 1,247 people with a login could read anybody's pay by editing a number.
 *
 * A payslip is the most private record in the system. These tests exist so the
 * scoping cannot quietly go missing again.
 */
class PayslipAccessTest extends TestCase
{
    use RefreshDatabase;

    private function employeeUser(string $first, string $role = 'employee'): array
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create();
        $user->assignRole($role);

        $employee = Employee::create([
            'user_id'    => $user->id,
            'emp_number' => 'MM' . $user->id,
            'first_name' => $first,
            'last_name'  => 'Test',
            'hire_date'  => now()->subYear(),
            'status'     => 'active',
        ]);

        return [$user, $employee];
    }

    private function payslipFor(Employee $employee, int $month): Payslip
    {
        $run = PayrollRun::create([
            'title'  => "Payroll {$month}/2026",
            'month'  => $month,
            'year'   => 2026,
            'status' => 'paid',
        ]);

        return Payslip::create([
            'payroll_run_id' => $run->id,
            'employee_id'    => $employee->id,
            'basic_salary'   => 1_000_000,
            'gross_salary'   => 1_000_000,
            'net_salary'     => 900_000,
            'payment_status' => 'paid',
        ]);
    }

    private function url(Payslip $slip): string
    {
        return "/payroll/{$slip->payroll_run_id}/payslips/{$slip->employee_id}/pdf";
    }

    public function test_an_employee_can_open_their_own_payslip(): void
    {
        [$user, $employee] = $this->employeeUser('Sande');
        $slip = $this->payslipFor($employee, 1);

        $this->actingAs($user)->get($this->url($slip))->assertOk();
    }

    public function test_an_employee_cannot_open_somebody_elses_payslip(): void
    {
        [$user]      = $this->employeeUser('Sande');
        [, $other]   = $this->employeeUser('Ashiraf');
        $theirs      = $this->payslipFor($other, 2);

        $this->actingAs($user)->get($this->url($theirs))->assertForbidden();
    }

    public function test_a_user_with_no_employee_record_cannot_open_a_payslip(): void
    {
        Role::findOrCreate('employee', 'web');
        $stranger = User::factory()->create();
        $stranger->assignRole('employee');

        [, $someone] = $this->employeeUser('Ashiraf');
        $slip        = $this->payslipFor($someone, 3);

        $this->actingAs($stranger)->get($this->url($slip))->assertForbidden();
    }

    public function test_payroll_staff_can_open_anybody_s_payslip(): void
    {
        [, $someone] = $this->employeeUser('Ashiraf');
        $slip        = $this->payslipFor($someone, 4);

        foreach (['super-admin', 'hr-admin', 'payroll-officer', 'md'] as $role) {
            Role::findOrCreate($role, 'web');
            $staff = User::factory()->create();
            $staff->assignRole($role);

            $this->actingAs($staff)->get($this->url($slip))
                ->assertOk("{$role} has to be able to open a payslip.");
        }
    }

    public function test_an_account_manager_sees_only_their_own_clients_employees(): void
    {
        Role::findOrCreate('account-manager', 'web');
        Role::findOrCreate('client', 'web');

        $am    = User::factory()->create();
        $am->assignRole('account-manager');

        $mine = Client::create([
            'company_name'       => 'Mine Ltd',
            'contact_person'     => 'A Contact',
            'user_id'            => User::factory()->create()->id,
            'account_manager_id' => $am->id,
        ]);
        $notMine = Client::create([
            'company_name'   => 'Somebody Else Ltd',
            'contact_person' => 'B Contact',
            'user_id'        => User::factory()->create()->id,
        ]);

        [, $onMyClient]  = $this->employeeUser('Ours');
        [, $onTheirs]    = $this->employeeUser('Theirs');
        $onMyClient->clients()->attach($mine->id, ['assigned_by' => $am->id]);
        $onTheirs->clients()->attach($notMine->id, ['assigned_by' => $am->id]);

        $this->actingAs($am)->get($this->url($this->payslipFor($onMyClient, 5)))->assertOk();
        $this->actingAs($am)->get($this->url($this->payslipFor($onTheirs, 6)))->assertForbidden();
    }

    // ── The list the web portal never had ───────────────────────────────────

    public function test_my_payslips_lists_released_runs_only(): void
    {
        [$user, $employee] = $this->employeeUser('Sande');

        $paid = $this->payslipFor($employee, 7);

        // A run still in the approval chain is a draft figure, not somebody's pay.
        $pending = PayrollRun::create(['title' => 'Aug', 'month' => 8, 'year' => 2026, 'status' => 'processed']);
        Payslip::create([
            'payroll_run_id' => $pending->id,
            'employee_id'    => $employee->id,
            'basic_salary'   => 1, 'gross_salary' => 1, 'net_salary' => 777_777,
            'payment_status' => 'pending',
        ]);

        $this->actingAs($user)->get('/employee/my-payslips')
            ->assertOk()
            ->assertSee(number_format((float) $paid->net_salary))
            ->assertDontSee('777,777');
    }

    public function test_my_payslips_shows_a_withheld_slip_and_its_reason(): void
    {
        [$user, $employee] = $this->employeeUser('Sande');
        $slip = $this->payslipFor($employee, 9);
        $slip->update(['payment_status' => 'withheld', 'withheld_reason' => 'Bank details missing.']);

        $this->actingAs($user)->get('/employee/my-payslips')
            ->assertOk()
            ->assertSee('Withheld')
            ->assertSee('Bank details missing.');
    }
}
