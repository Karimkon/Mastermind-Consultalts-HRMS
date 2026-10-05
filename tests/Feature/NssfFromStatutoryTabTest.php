<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\PayrollRun;
use App\Models\SalaryComponent;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Charge NSSF" in Employee Central has to actually charge NSSF.
 *
 * It did not. NSSF was deducted only when an NSSF_EMP component had been
 * attached to the salary on the Salary Setup screen; the tickbox on the
 * Statutory tab was read by nobody. On Bidco all 67 staff were flagged and none
 * had the component, so neither the employee's 5% nor the employer's 10% was
 * ever taken.
 *
 * A tickbox that says "Charge NSSF" and charges nothing is worse than no
 * tickbox: somebody ticked it and believed it.
 *
 * An attached component still wins, so salaries that were configured
 * deliberately keep exactly the figures they had.
 */
class NssfFromStatutoryTabTest extends TestCase
{
    use RefreshDatabase;

    private PayrollService $payroll;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payroll = app(PayrollService::class);
    }

    private function employee(array $flags = []): Employee
    {
        static $n = 0;
        $n++;

        $e = Employee::create(array_merge([
            'emp_number' => 'NSSF-' . $n,
            'first_name' => 'Abed',
            'last_name' => 'Sande',
            'hire_date' => '2020-01-01',
            'status' => 'active',
            'charge_paye' => true,
            'charge_nssf' => true,
        ], $flags));

        EmployeeSalary::create([
            'employee_id' => $e->id,
            'basic_salary' => 455000,
            'salary_type' => 'monthly',
            'effective_from' => '2020-01-01',
            'is_current' => true,
        ]);

        // Without attendance the engine pays nothing, basic prorates to zero,
        // and 5% of zero is zero — which would make every assertion below pass
        // for the wrong reason.
        $rows = [];
        for ($d = 1; $d <= 31; $d++) {
            $rows[] = [
                'employee_id' => $e->id,
                'date' => sprintf('2026-10-%02d', $d),
                'status' => 'present',
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        \Illuminate\Support\Facades\DB::table('attendance_logs')->insert($rows);

        return $e;
    }

    /** One run per test: payroll_runs is unique on (month, year). */
    private function payrollRun(): PayrollRun
    {
        return PayrollRun::firstOrCreate(
            ['month' => 10, 'year' => 2026],
            ['title' => 'October 2026', 'status' => 'draft']
        );
    }

    private function nssfLine(array $figures): ?array
    {
        return collect($figures['component_details'] ?? [])
            ->firstWhere('code', 'NSSF_EMP');
    }

    // ===== The fix =====

    public function test_ticking_charge_nssf_deducts_five_percent(): void
    {
        $e = $this->employee();

        $f = $this->payroll->calculatePayslip($e, $this->payrollRun());

        $this->assertGreaterThan(0, $f['basic_salary'], 'The engine paid nothing; the test proves nothing.');
        $this->assertGreaterThan(0, $f['employee_nssf'], 'Charge NSSF is ticked but nothing was deducted.');
        $this->assertEqualsWithDelta(
            round($f['basic_salary'] * 0.05),
            $f['employee_nssf'],
            1.0,
            'NSSF is not 5% of basic.'
        );
        $this->assertNotNull($this->nssfLine($f), 'NSSF was deducted but does not appear on the payslip.');
    }

    public function test_it_reaches_the_deductions_total_and_the_net(): void
    {
        $withNssf = $this->payroll->calculatePayslip($this->employee(), $this->payrollRun());
        $without  = $this->payroll->calculatePayslip(
            $this->employee(['charge_nssf' => false]), $this->payrollRun()
        );

        $this->assertGreaterThan($without['total_deductions'], $withNssf['total_deductions']);
        $this->assertLessThan($without['net_salary'], $withNssf['net_salary'],
            'NSSF was shown but did not come out of net pay.');

        // And the payslip still adds up.
        $this->assertEqualsWithDelta(
            $withNssf['gross_salary'] - $withNssf['total_deductions'],
            $withNssf['net_salary'],
            1.0
        );
    }

    public function test_unticking_it_deducts_nothing(): void
    {
        $f = $this->payroll->calculatePayslip(
            $this->employee(['charge_nssf' => false]), $this->payrollRun()
        );

        $this->assertSame(0.0, (float) $f['employee_nssf']);
        $this->assertNull($this->nssfLine($f));
    }

    public function test_the_employer_ten_percent_follows(): void
    {
        $f = $this->payroll->calculatePayslip($this->employee(), $this->payrollRun());

        $this->assertEqualsWithDelta($f['employee_nssf'] * 2, $f['employer_nssf'], 1.0,
            "The employer's share is not twice the employee's.");
    }

    // ===== The other switches on that tab =====

    public function test_do_not_charge_employee_takes_nothing_from_them(): void
    {
        $f = $this->payroll->calculatePayslip(
            $this->employee(['do_not_charge_nssf_employee' => true]), $this->payrollRun()
        );

        $this->assertSame(0.0, (float) $f['employee_nssf']);
    }

    public function test_paid_by_employer_is_shown_but_not_taken_from_pay(): void
    {
        $e = $this->employee(['nssf_paid_by_employer' => true]);
        $f = $this->payroll->calculatePayslip($e, $this->payrollRun());
        $plain = $this->payroll->calculatePayslip(
            $this->employee(['charge_nssf' => false]), $this->payrollRun()
        );

        $this->assertGreaterThan(0, $f['employee_nssf'], 'It should still be recorded and remitted.');
        $this->assertNotNull($this->nssfLine($f), 'It should still be on the payslip.');
        $this->assertEqualsWithDelta($plain['net_salary'], $f['net_salary'], 1.0,
            'The employer was paying it, but it still came out of the employee.');
    }

    public function test_a_fixed_amount_is_used_when_forced(): void
    {
        $f = $this->payroll->calculatePayslip(
            $this->employee(['force_fixed_nssf' => true, 'fixed_nssf_amount' => 12000]),
            $this->payrollRun()
        );

        $this->assertSame(12000.0, (float) $f['employee_nssf']);
    }

    // ===== What must not change =====

    /** A salary configured deliberately keeps the figures it had. */
    public function test_an_attached_component_still_wins(): void
    {
        $component = SalaryComponent::create([
            'name' => 'NSSF (Employee 5%)',
            'code' => 'NSSF_EMP',
            'type' => 'deduction',
            'is_fixed' => true,
            'amount' => 7777,
            'is_active' => true,
        ]);

        $e = $this->employee();
        EmployeeSalary::where('employee_id', $e->id)->update([
            'components' => json_encode([['component_id' => $component->id, 'amount' => 7777]]),
        ]);

        $f = $this->payroll->calculatePayslip($e->fresh(), $this->payrollRun());

        $this->assertSame(7777.0, (float) $f['employee_nssf'],
            'The attached component was overridden by the derived figure.');

        // And it is not counted twice.
        $lines = collect($f['component_details'])->where('code', 'NSSF_EMP');
        $this->assertCount(1, $lines, 'NSSF appears on the payslip twice.');
    }

    public function test_nssf_is_not_double_counted_in_deductions(): void
    {
        $f = $this->payroll->calculatePayslip($this->employee(), $this->payrollRun());

        $lineTotal = collect($f['component_details'])
            ->where('type', 'deduction')
            ->sum(fn ($l) => (float) $l['amount']);

        $this->assertEqualsWithDelta($f['total_deductions'], $lineTotal, 1.0,
            'The deduction lines do not sum to total_deductions.');
    }
}
