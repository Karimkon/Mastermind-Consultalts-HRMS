<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\PayrollRun;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Withholding tax, 6% of the gross payment.
 *
 * Not PAYE. PAYE is an employee's income tax on a graduated scale; WHT is a flat
 * percentage withheld on a payment for services. Someone on a consultancy
 * arrangement gets one, a salaried employee the other, so they are separate
 * switches and a payslip can legitimately carry either or both.
 */
class WithholdingTaxTest extends TestCase
{
    use RefreshDatabase;

    private PayrollService $payroll;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payroll = app(PayrollService::class);
    }

    private function employee(array $attributes = []): Employee
    {
        static $n = 0;
        $n++;

        return Employee::create(array_merge([
            'emp_number' => 'WHT-'.$n,
            'first_name' => 'Test',
            'last_name' => 'Consultant '.$n,
            'hire_date' => '2020-01-01',
            'status' => 'active',
        ], $attributes));
    }

    private function salary(Employee $e, float $rate, ?array $components = null): EmployeeSalary
    {
        return EmployeeSalary::create([
            'employee_id' => $e->id,
            'basic_salary' => $rate,
            'salary_type' => 'monthly',
            'components' => $components,
            'effective_from' => '2020-01-01',
            'is_current' => true,
        ]);
    }

    private function payrollRun(): PayrollRun
    {
        return PayrollRun::create([
            'title' => 'September 2026',
            'month' => 9,
            'year' => 2026,
            'status' => 'draft',
        ]);
    }

    /** Written through the query builder for the reason PayrollCalculationTest documents. */
    private function attend(Employee $e, int $days): void
    {
        for ($d = 1; $d <= $days; $d++) {
            DB::table('attendance_logs')->insert([
                'employee_id' => $e->id,
                'date' => sprintf('2026-09-%02d', $d),
                'status' => 'present',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function payslipLine(Employee $e, string $code): ?array
    {
        $payslip = $this->payroll->processEmployee($e, $this->payrollRun());

        foreach ($payslip->component_details ?? [] as $row) {
            if (($row['code'] ?? null) === $code) {
                return $row + ['_payslip' => $payslip];
            }
        }

        return null;
    }

    // ── The sum ──────────────────────────────────────────────────────────

    public function test_withholding_tax_is_six_percent_of_the_gross(): void
    {
        $employee = $this->employee(['charge_wht' => true, 'charge_paye' => false, 'charge_nssf' => false]);
        $this->salary($employee, 1_200_000);
        $this->attend($employee, 30);

        $payslip = $this->payroll->processEmployee($employee, $this->payrollRun());

        $gross = (float) $payslip->gross_salary;
        $expected = round($gross * 0.06, 0);

        $this->assertGreaterThan(0, $expected);
        $this->assertSame($expected, (float) $payslip->total_deductions);
        $this->assertSame($gross - $expected, (float) $payslip->net_salary);
    }

    public function test_six_percent_is_the_default_rate(): void
    {
        $employee = $this->employee(['charge_wht' => true]);

        $this->assertSame('6.00', (string) $employee->refresh()->wht_percentage);
    }

    /** The rate is read from the employee, not assumed, so a changed rate follows. */
    public function test_a_different_rate_is_honoured(): void
    {
        $employee = $this->employee([
            'charge_wht' => true, 'wht_percentage' => 10,
            'charge_paye' => false, 'charge_nssf' => false,
        ]);
        $this->salary($employee, 1_200_000);
        $this->attend($employee, 30);

        $payslip = $this->payroll->processEmployee($employee, $this->payrollRun());

        $this->assertSame(
            round((float) $payslip->gross_salary * 0.10, 0),
            (float) $payslip->total_deductions
        );
    }

    // ── When it does and does not apply ──────────────────────────────────

    public function test_nothing_is_withheld_unless_the_flag_is_set(): void
    {
        $employee = $this->employee(['charge_paye' => false, 'charge_nssf' => false]);
        $this->salary($employee, 1_200_000);
        $this->attend($employee, 30);

        $payslip = $this->payroll->processEmployee($employee, $this->payrollRun());

        $codes = array_column($payslip->component_details ?? [], 'code');

        $this->assertNotContains('WHT', $codes);
        $this->assertSame(0.0, (float) $payslip->total_deductions);
    }

    /** The default must not start withholding from everybody on 905 payslips. */
    public function test_an_ordinary_employee_is_not_withheld(): void
    {
        $employee = $this->employee();

        $this->assertFalse((bool) $employee->refresh()->charge_wht);
    }

    // ── It is not PAYE ───────────────────────────────────────────────────

    /**
     * tax_amount is the PAYE column, read by the URA return and the payslip's
     * tax line. WHT landing in it would overstate PAYE.
     */
    public function test_withholding_tax_does_not_land_in_the_paye_column(): void
    {
        $employee = $this->employee(['charge_wht' => true, 'charge_paye' => false, 'charge_nssf' => false]);
        $this->salary($employee, 1_200_000);
        $this->attend($employee, 30);

        $payslip = $this->payroll->processEmployee($employee, $this->payrollRun());

        $this->assertSame(0.0, (float) $payslip->tax_amount);
        $this->assertGreaterThan(0, (float) $payslip->total_deductions);
    }

    public function test_both_taxes_can_apply_at_once(): void
    {
        $employee = $this->employee(['charge_wht' => true, 'charge_paye' => true, 'charge_nssf' => false]);
        $this->salary($employee, 1_200_000);
        $this->attend($employee, 30);

        $payslip = $this->payroll->processEmployee($employee, $this->payrollRun());

        $codes = array_column($payslip->component_details ?? [], 'code');

        $this->assertContains('PAYE', $codes);
        $this->assertContains('WHT', $codes);

        $gross = (float) $payslip->gross_salary;

        $this->assertEqualsWithDelta(
            (float) $payslip->tax_amount + round($gross * 0.06, 0),
            (float) $payslip->total_deductions,
            1.0
        );
    }

    public function test_the_line_names_the_rate_that_was_applied(): void
    {
        $employee = $this->employee(['charge_wht' => true, 'charge_paye' => false, 'charge_nssf' => false]);
        $this->salary($employee, 1_200_000);
        $this->attend($employee, 30);

        $row = $this->payslipLine($employee, 'WHT');

        $this->assertNotNull($row, 'No WHT line on the payslip.');
        $this->assertSame('Withholding Tax (6%)', $row['name']);
        $this->assertSame('deduction', $row['type']);
    }

    public function test_it_prints_with_the_taxes_not_at_the_bottom(): void
    {
        // NSSF only appears as a line when the component is attached to the
        // salary; charge_nssf alone does not emit one.
        $nssf = SalaryComponent::create([
            'name' => 'NSSF (Employee 5%)',
            'code' => 'NSSF_EMP',
            'type' => 'deduction',
            'is_fixed' => false,
            'percentage' => 5,
            'is_active' => true,
        ]);

        $employee = $this->employee(['charge_wht' => true, 'charge_paye' => true, 'charge_nssf' => true]);
        $this->salary($employee, 1_200_000, [['component_id' => $nssf->id, 'percentage' => 5]]);
        $this->attend($employee, 30);

        $payslip = $this->payroll->processEmployee($employee, $this->payrollRun());

        $codes = array_values(array_filter(
            array_column($payslip->orderedComponents(), 'code'),
            fn ($c) => in_array($c, ['PAYE', 'WHT', 'NSSF_EMP'], true)
        ));

        $this->assertSame(['PAYE', 'WHT', 'NSSF_EMP'], $codes);
    }

    // ── The form ─────────────────────────────────────────────────────────

    private function admin(): User
    {
        Role::findOrCreate('super-admin', 'web');

        $user = User::factory()->create();
        $user->assignRole('super-admin');

        return $user;
    }

    public function test_the_statutory_tab_can_switch_withholding_on(): void
    {
        $employee = $this->employee();

        $this->actingAs($this->admin())
            ->put(route('employees.update', $employee), [
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'charge_wht' => '1',
                'wht_percentage' => '6',
            ])
            ->assertRedirect();

        $employee->refresh();

        $this->assertTrue((bool) $employee->charge_wht);
        $this->assertSame('6.00', (string) $employee->wht_percentage);
    }

    /**
     * charge_paye was rendered on this form, ticked, saved — and dropped. It was
     * in the model's fillable and in the Blade, but in neither the controller's
     * field list nor its boolean list, so the only way to exempt somebody from
     * PAYE was a different screen or a spreadsheet import.
     */
    public function test_the_statutory_tab_can_exempt_somebody_from_paye(): void
    {
        $employee = $this->employee(['charge_paye' => true]);

        $this->actingAs($this->admin())
            ->put(route('employees.update', $employee), [
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                // charge_paye omitted, the way an unticked checkbox arrives.
            ])
            ->assertRedirect();

        $this->assertFalse((bool) $employee->refresh()->charge_paye);
    }

    public function test_the_statutory_tab_can_switch_paye_back_on(): void
    {
        $employee = $this->employee(['charge_paye' => false]);

        $this->actingAs($this->admin())
            ->put(route('employees.update', $employee), [
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'charge_paye' => '1',
            ])
            ->assertRedirect();

        $this->assertTrue((bool) $employee->refresh()->charge_paye);
    }
}
