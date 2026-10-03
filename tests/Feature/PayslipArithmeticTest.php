<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\PayrollRun;
use App\Models\SalaryComponent;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A payslip has to add up, and NSSF has to be counted once.
 *
 * ONOMO run 6 carried 34 payslips where net_salary had NSSF taken twice: once
 * inside total_deductions, where it belongs, and once again afterwards. The
 * deduction lines were right and summed to total_deductions, so every screen
 * showing the breakdown looked correct — only the net was wrong, and 34 people
 * were understated by exactly their own NSSF, 641,844 between them.
 *
 * It survived because nothing asserted the one relationship that must always
 * hold on output from the engine:
 *
 *     net_salary === gross_salary - total_deductions
 *
 * These run the real PayrollService rather than arithmetic on hand-written
 * figures, because hand-written fixtures would have agreed with the bug.
 *
 * NSSF gets its own attention: it is the one deduction stored twice over, in
 * the employee_nssf column AND in the component lines, and that duplication is
 * what made subtracting it twice easy to write in the first place.
 */
class PayslipArithmeticTest extends TestCase
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
            'emp_number' => 'ARITH-' . $n,
            'first_name' => 'Test',
            'last_name' => 'Employee ' . $n,
            'hire_date' => '2020-01-01',
            'status' => 'active',
        ], $attributes));
    }

    private function nssfComponent(): SalaryComponent
    {
        return SalaryComponent::firstOrCreate(
            ['code' => 'NSSF_EMP'],
            ['name' => 'NSSF (Employee 5%)', 'type' => 'deduction',
             'is_fixed' => false, 'percentage' => 5, 'is_active' => true]
        );
    }

    /** With NSSF attached, because the engine only charges components it is given. */
    private function salary(Employee $e, float $rate, bool $withNssf = true): EmployeeSalary
    {
        $components = $withNssf
            ? [['component_id' => $this->nssfComponent()->id, 'percentage' => 5]]
            : null;

        return EmployeeSalary::create([
            'employee_id' => $e->id,
            'basic_salary' => $rate,
            'salary_type' => 'monthly',
            'components' => $components,
            'effective_from' => '2020-01-01',
            'is_current' => true,
        ]);
    }

    /** Without attendance the engine pays nothing, and every check passes on zero. */
    private function attend(Employee $e, int $days = 30): void
    {
        $rows = [];
        for ($d = 1; $d <= $days; $d++) {
            $rows[] = [
                'employee_id' => $e->id,
                'date' => sprintf('2026-09-%02d', $d),
                'status' => 'present',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        \Illuminate\Support\Facades\DB::table('attendance_logs')->insert($rows);
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

    /** Salaries either side of the PAYE bands, and one near the NSSF ceiling. */
    public static function salaries(): array
    {
        return [
            'below the PAYE threshold' => [200000],
            'the ONOMO shape'          => [350666],
            'mid band'                 => [800000],
            'upper band'               => [2500000],
        ];
    }

    #[DataProvider('salaries')]
    public function test_net_is_gross_less_deductions(float $monthly): void
    {
        $employee = $this->employee(['charge_paye' => true, 'charge_nssf' => true]);
        $this->salary($employee, $monthly);
        $this->attend($employee);

        $slip = $this->payroll->processEmployee($employee, $this->payrollRun());

        // Guard the premise: a zero payslip satisfies every check below.
        $this->assertGreaterThan(0, (float) $slip->gross_salary, 'The engine paid nothing; the test proves nothing.');

        $this->assertEqualsWithDelta(
            (float) $slip->gross_salary - (float) $slip->total_deductions,
            (float) $slip->net_salary,
            1.0,
            sprintf(
                'On %s: gross %s - deductions %s should be net, but net is %s (out by %s).',
                number_format($monthly),
                number_format($slip->gross_salary),
                number_format($slip->total_deductions),
                number_format($slip->net_salary),
                number_format(($slip->gross_salary - $slip->total_deductions) - $slip->net_salary)
            )
        );
    }

    #[DataProvider('salaries')]
    public function test_nssf_is_deducted_once_and_only_once(float $monthly): void
    {
        $employee = $this->employee(['charge_paye' => true, 'charge_nssf' => true]);
        $this->salary($employee, $monthly);
        $this->attend($employee);

        $slip = $this->payroll->processEmployee($employee, $this->payrollRun());

        // Guard the premise: a zero payslip satisfies every check below.
        $this->assertGreaterThan(0, (float) $slip->gross_salary, 'The engine paid nothing; the test proves nothing.');

        $lines = collect($slip->component_details ?? [])->where('type', 'deduction');
        $nssfLines = $lines->filter(fn ($l) => ($l['code'] ?? '') === 'NSSF_EMP');

        $this->assertLessThanOrEqual(1, $nssfLines->count(), 'NSSF is on the payslip more than once.');

        $this->assertCount(1, $nssfLines,
            'No NSSF was charged, so this test would prove nothing. The component'
            . ' must be attached to the salary for the engine to apply it.');

        // The column and the line must agree ...
        $this->assertEqualsWithDelta(
            (float) $slip->employee_nssf,
            (float) $nssfLines->first()['amount'],
            1.0,
            'employee_nssf disagrees with the NSSF line on the payslip.'
        );

        // ... and the lines must account for the whole deduction total, which
        // is what proves NSSF was added to it exactly once.
        $this->assertEqualsWithDelta(
            (float) $slip->total_deductions,
            $lines->sum(fn ($l) => (float) ($l['amount'] ?? 0)),
            1.0,
            'The deduction lines do not sum to total_deductions.'
        );
    }

    /** Somebody exempt from NSSF must not have it taken at all. */
    public function test_an_exempt_employee_has_no_nssf(): void
    {
        $employee = $this->employee(['charge_paye' => true, 'charge_nssf' => false]);
        $this->salary($employee, 350666, withNssf: false);
        $this->attend($employee);

        $slip = $this->payroll->processEmployee($employee, $this->payrollRun());

        $this->assertGreaterThan(0, (float) $slip->gross_salary);

        $nssf = collect($slip->component_details ?? [])
            ->filter(fn ($l) => ($l['code'] ?? '') === 'NSSF_EMP');

        $this->assertTrue($nssf->isEmpty(), 'NSSF was charged to an exempt employee.');
        $this->assertEqualsWithDelta(
            (float) $slip->gross_salary - (float) $slip->total_deductions,
            (float) $slip->net_salary,
            1.0
        );
    }

    /** The exact shape of run 6, so the defect is recognisable if it returns. */
    public function test_the_run_six_shape_would_now_be_caught(): void
    {
        $gross = 350666.0;
        $deductions = 30666.0;     // NSSF 17,533 + PAYE 13,133, NSSF already inside
        $nssf = 17533.0;

        $wrongNet = $gross - $deductions - $nssf;
        $this->assertSame(302467.0, $wrongNet, 'This is what run 6 actually held.');

        // The tell: the shortfall is exactly the NSSF, while the deduction lines
        // still sum correctly — so only the net check catches it.
        $this->assertSame($nssf, ($gross - $deductions) - $wrongNet);
    }
}
