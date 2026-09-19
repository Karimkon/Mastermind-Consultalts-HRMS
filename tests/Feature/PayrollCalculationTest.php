<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\PayrollRun;
use App\Models\SalaryComponent;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The arithmetic that pays people.
 *
 * This is the part of the system that hands real money to 906 employees, and it
 * had no tests at all. Everything else can be wrong and be noticed; a payroll
 * that is wrong by a few thousand shillings per person is paid, banked, and
 * discovered a month later by somebody reading their payslip.
 *
 * The PAYE figures below are not derived from the implementation — they are
 * computed from the URA resident monthly bands and written out by hand, which is
 * the only way a test of a calculation is worth anything:
 *
 *     up to 235,000        nil
 *     235,001 – 335,000    10% of the excess over 235,000
 *     335,001 – 410,000    10,000 + 20% of the excess over 335,000
 *     410,001 – 10,000,000 25,000 + 30% of the excess over 410,000
 *     over 10,000,000      2,902,000 + 40% of the excess over 10,000,000
 *
 * The last band is the two published rules added together: 30% continues and a
 * further 10% applies above ten million, so the marginal rate is 40%.
 */
class PayrollCalculationTest extends TestCase
{
    use RefreshDatabase;

    private PayrollService $payroll;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payroll = app(PayrollService::class);
    }

    private function paye(float $taxable): float
    {
        $method = new ReflectionMethod(PayrollService::class, 'calculatePAYE');
        $method->setAccessible(true);

        return $method->invoke($this->payroll, $taxable);
    }

    // ── PAYE ─────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{float, float}>
     */
    public static function payeBands(): array
    {
        return [
            'nothing at all' => [0, 0],
            'below the threshold' => [200_000, 0],
            // The threshold itself is still free of tax.
            'exactly at the threshold' => [235_000, 0],
            'one shilling over' => [235_001, 0],
            'inside the 10% band' => [300_000, 6_500],
            'top of the 10% band' => [335_000, 10_000],
            'inside the 20% band' => [400_000, 23_000],
            'top of the 20% band' => [410_000, 25_000],
            'inside the 30% band' => [500_000, 52_000],
            'a million' => [1_000_000, 202_000],
            'five million' => [5_000_000, 1_402_000],
            // 25,000 + 30% of 9,590,000.
            'top of the 30% band' => [10_000_000, 2_902_000],
            'into the additional 10%' => [12_000_000, 3_702_000],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('payeBands')]
    public function test_paye_matches_the_published_bands(float $taxable, float $expected): void
    {
        $this->assertSame(
            $expected,
            $this->paye($taxable),
            sprintf('PAYE on %s should be %s.', number_format($taxable), number_format($expected))
        );
    }

    /**
     * Tax must never fall as pay rises.
     *
     * A band written with the wrong base — the commonest way a bracket table goes
     * wrong — shows up as a step backwards at the boundary, which no single-point
     * assertion would catch.
     */
    public function test_paye_never_decreases_as_pay_rises(): void
    {
        $previous = -1.0;

        for ($taxable = 0; $taxable <= 12_000_000; $taxable += 25_000) {
            $tax = $this->paye($taxable);

            $this->assertGreaterThanOrEqual(
                $previous,
                $tax,
                sprintf('PAYE fell between %s and %s.', number_format($taxable - 25_000), number_format($taxable))
            );

            $previous = $tax;
        }
    }

    /** Nobody may be taxed more than they earned. */
    public function test_paye_never_exceeds_the_pay_it_is_charged_on(): void
    {
        foreach ([235_001, 300_000, 500_000, 1_000_000, 10_000_000, 12_000_000] as $taxable) {
            $this->assertLessThan($taxable, $this->paye($taxable));
        }
    }

    // ── Gross-up ─────────────────────────────────────────────────────────

    /**
     * Gross-up is only correct if it inverts.
     *
     * Some clients agree a take-home figure and carry the tax themselves. The
     * question the function answers is "what gross leaves exactly this net after
     * PAYE and the employee's 5% NSSF", so the test asks the same question
     * backwards: take the gross it returns, deduct PAYE and NSSF, and see whether
     * the original net comes back.
     *
     * A shilling of rounding either way is acceptable; ten thousand is not.
     */
    public function test_grossing_up_inverts_paye_and_nssf(): void
    {
        foreach ([200_000, 300_000, 450_000, 700_000, 1_500_000, 4_000_000] as $net) {
            $gross = $this->payroll->grossUpAmount($net);
            $recovered = $gross - $this->paye($gross) - ($gross * 0.05);

            $this->assertEqualsWithDelta(
                $net,
                $recovered,
                1.0,
                sprintf('Grossing %s up gave %s, which nets back to %s.',
                    number_format($net), number_format($gross), number_format($recovered))
            );
        }
    }

    public function test_grossing_up_always_produces_more_than_the_net(): void
    {
        foreach ([200_000, 500_000, 2_000_000] as $net) {
            $this->assertGreaterThan($net, $this->payroll->grossUpAmount($net));
        }
    }

    // ── Fixtures for the end-to-end sums ─────────────────────────────────

    private function employee(array $attributes = []): Employee
    {
        static $n = 0;
        $n++;

        return Employee::create(array_merge([
            'emp_number' => 'EMP-'.$n,
            'first_name' => 'Test',
            'last_name' => 'Employee '.$n,
            'hire_date' => '2020-01-01',
            'status' => 'active',
        ], $attributes));
    }

    private function salary(Employee $e, float $rate, string $type = 'monthly', ?array $components = null): EmployeeSalary
    {
        return EmployeeSalary::create([
            'employee_id' => $e->id,
            'basic_salary' => $rate,
            'salary_type' => $type,
            'components' => $components,
            'effective_from' => '2020-01-01',
            'is_current' => true,
        ]);
    }

    private function payrollRun(): PayrollRun
    {
        // September 2026: 30 calendar days. Chosen because the monthly formula is
        // annual ÷ 365 × days, so a 30-day month makes the expected figure easy
        // to state independently of the code.
        return PayrollRun::create([
            'title' => 'September 2026',
            'month' => 9,
            'year' => 2026,
            'status' => 'draft',
        ]);
    }

    /**
     * Attendance written the way the production column actually holds it.
     *
     * `attendance_logs.date` is a real DATE in MySQL and the model casts it to
     * `date`, so live rows hold `2026-09-30` and PayrollService's
     * `whereBetween('date', [start, end])` matches the whole month.
     *
     * SQLite has no date type. Eloquent serialises even a `date` cast using the
     * connection's datetime format, so the same create() stores
     * `2026-09-30 00:00:00`, which sorts *after* the string `2026-09-30` and drops
     * the last day of every month — one day's pay per person, in the test
     * environment only.
     *
     * Inserting through the query builder keeps the fixture faithful to
     * production rather than quietly testing a different schema. The alternative,
     * loosening the service's query to satisfy SQLite, would change working
     * production code to suit a test database.
     */
    private function attend(Employee $e, int $days, string $status = 'present'): void
    {
        $rows = [];

        for ($d = 1; $d <= $days; $d++) {
            $rows[] = [
                'employee_id' => $e->id,
                'date' => sprintf('2026-09-%02d', $d),
                'status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        \Illuminate\Support\Facades\DB::table('attendance_logs')->insert($rows);
    }

    /** Authorised overtime on one day, written the same way. */
    private function approveOvertime(Employee $e, string $date, float $clocked, float $approved, string $status): void
    {
        \Illuminate\Support\Facades\DB::table('attendance_logs')
            ->where('employee_id', $e->id)
            ->where('date', $date)
            ->update([
                'overtime_hours' => $clocked,
                'approved_overtime_hours' => $approved,
                'overtime_status' => $status,
            ]);
    }

    // ── Monthly pay ──────────────────────────────────────────────────────

    /**
     * Monthly pay is annual ÷ 365 × days worked, not monthly ÷ days-in-month.
     *
     * The difference is not academic: on a 1,200,000 salary the two give 1,183,562
     * and 1,200,000 for a full 30-day September, and the choice has to be the
     * same every month or people are paid differently in February.
     */
    public function test_monthly_pay_is_prorated_over_a_365_day_year(): void
    {
        $employee = $this->employee();
        $this->salary($employee, 1_200_000);
        $this->attend($employee, 30);

        $payslip = $this->payroll->processEmployee($employee, $this->payrollRun());

        // 1,200,000 × 12 ÷ 365 × 30 = 1,183,561.6...
        $this->assertSame(1_183_562.0, (float) $payslip->basic_salary);
        $this->assertSame(30, (int) $payslip->worked_days);
    }

    public function test_half_a_month_is_paid_at_half(): void
    {
        $employee = $this->employee();
        $this->salary($employee, 1_200_000);
        $this->attend($employee, 15);

        $payslip = $this->payroll->processEmployee($employee, $this->payrollRun());

        // Same daily rate, half the days.
        $this->assertSame(591_781.0, (float) $payslip->basic_salary);
    }

    public function test_paye_is_deducted_from_the_gross(): void
    {
        $employee = $this->employee();
        $this->salary($employee, 1_200_000);
        $this->attend($employee, 30);

        $payslip = $this->payroll->processEmployee($employee, $this->payrollRun());

        $gross = (float) $payslip->gross_salary;
        $expectedPaye = $this->paye($gross);

        $this->assertSame($expectedPaye, (float) $payslip->tax_amount);
        $this->assertSame($gross - $expectedPaye, (float) $payslip->net_salary);
    }

    /**
     * Some staff are engaged on terms where the company does not deduct PAYE.
     * The flag has to actually stop it.
     */
    public function test_an_employee_marked_exempt_is_not_taxed(): void
    {
        $employee = $this->employee(['charge_paye' => false]);
        $this->salary($employee, 1_200_000);
        $this->attend($employee, 30);

        $payslip = $this->payroll->processEmployee($employee, $this->payrollRun());

        $this->assertSame(0.0, (float) $payslip->tax_amount);
        $this->assertSame((float) $payslip->gross_salary, (float) $payslip->net_salary);
    }

    // ── Casual rates ─────────────────────────────────────────────────────

    public function test_daily_pay_is_the_rate_times_the_days(): void
    {
        $employee = $this->employee();
        $this->salary($employee, 20_000, 'daily');
        $this->attend($employee, 22);

        $payslip = $this->payroll->processEmployee($employee, $this->payrollRun());

        $this->assertSame(440_000.0, (float) $payslip->basic_salary);
    }

    public function test_hourly_pay_assumes_an_eight_hour_day(): void
    {
        $employee = $this->employee();
        $this->salary($employee, 3_000, 'hourly');
        $this->attend($employee, 20);

        $payslip = $this->payroll->processEmployee($employee, $this->payrollRun());

        // 3,000 × (20 × 8)
        $this->assertSame(480_000.0, (float) $payslip->basic_salary);
    }

    // ── Overtime ─────────────────────────────────────────────────────────

    /**
     * The most expensive rule in the file.
     *
     * `overtime_hours` is what the clock saw. `approved_overtime_hours` is what
     * somebody authorised. Paying the former meant overtime went out at 1.5×
     * with nobody signing it off, which is exactly the kind of leak that is only
     * ever found by an auditor.
     */
    public function test_unapproved_overtime_is_not_paid(): void
    {
        $employee = $this->employee();
        $this->salary($employee, 20_000, 'daily');
        $this->attend($employee, 20);

        $this->approveOvertime($employee, '2026-09-01', clocked: 10, approved: 10, status: 'pending');

        $payslip = $this->payroll->processEmployee($employee, $this->payrollRun());

        $this->assertSame(0, (int) $payslip->overtime_hours_paid);
        $this->assertSame(400_000.0, (float) $payslip->basic_salary);
    }

    public function test_approved_overtime_is_paid_at_time_and_a_half(): void
    {
        $employee = $this->employee();
        $this->salary($employee, 20_000, 'daily');
        $this->attend($employee, 20);

        $this->approveOvertime($employee, '2026-09-01', clocked: 10, approved: 10, status: 'approved');

        $payslip = $this->payroll->processEmployee($employee, $this->payrollRun());

        // Daily 20,000 ÷ 8 = 2,500/hour; 10 hours × 2,500 × 1.5 = 37,500.
        $this->assertSame(10, (int) $payslip->overtime_hours_paid);
        $this->assertSame(37_500.0, (float) $payslip->overtime_pay);
    }

    public function test_only_the_approved_hours_are_paid_not_the_clocked_ones(): void
    {
        $employee = $this->employee();
        $this->salary($employee, 20_000, 'daily');
        $this->attend($employee, 20);

        // Twelve hours on the clock, four of them authorised.
        $this->approveOvertime($employee, '2026-09-01', clocked: 12, approved: 4, status: 'approved');

        $payslip = $this->payroll->processEmployee($employee, $this->payrollRun());

        $this->assertSame(15_000.0, (float) $payslip->overtime_pay, '4 × 2,500 × 1.5');
    }

    // ── NSSF ─────────────────────────────────────────────────────────────

    /**
     * The employer's 10% is a company cost, not the employee's money.
     *
     * It must appear on the payslip — people are entitled to see it — without
     * being added to gross or taken out of net. Getting this wrong in either
     * direction changes what somebody is paid.
     */
    public function test_employer_nssf_is_ten_percent_and_changes_neither_gross_nor_net(): void
    {
        $component = SalaryComponent::create([
            'name' => 'NSSF (Employee 5%)',
            'code' => 'NSSF_EMP',
            'type' => 'deduction',
            'is_fixed' => false,
            'percentage' => 5,
            'is_active' => true,
        ]);

        $employee = $this->employee();
        $this->salary($employee, 1_000_000, 'monthly', [
            ['component_id' => $component->id, 'percentage' => 5],
        ]);
        $this->attend($employee, 30);

        $payslip = $this->payroll->processEmployee($employee, $this->payrollRun());

        $basic = (float) $payslip->basic_salary;
        $employeeNssf = (float) $payslip->employee_nssf;

        $this->assertSame(round($basic * 0.05, 0), $employeeNssf, "The employee's share is 5% of basic.");
        $this->assertSame($employeeNssf * 2, (float) $payslip->employer_nssf, 'The employer pays twice that.');

        // Gross is basic plus allowances only — the employer's contribution is
        // not pay and must not inflate it.
        $this->assertSame($basic, (float) $payslip->gross_salary);

        // And net is gross less the employee's own deductions.
        $expectedNet = $basic - $employeeNssf - (float) $payslip->tax_amount;
        $this->assertSame($expectedNet, (float) $payslip->net_salary);
    }

    // ── The whole payslip ────────────────────────────────────────────────

    /**
     * One payslip, every figure stated independently.
     *
     * Written out longhand rather than derived, so that if the service changes
     * this test says which number moved.
     */
    public function test_a_complete_payslip_adds_up(): void
    {
        $employee = $this->employee();
        $this->salary($employee, 1_500_000);
        $this->attend($employee, 30);

        $payslip = $this->payroll->processEmployee($employee, $this->payrollRun());

        // 1,500,000 × 12 ÷ 365 × 30 = 1,479,452.05 → 1,479,452
        $this->assertSame(1_479_452.0, (float) $payslip->basic_salary);
        $this->assertSame(1_479_452.0, (float) $payslip->gross_salary);

        // 25,000 + 30% of (1,479,452 − 410,000) = 25,000 + 320,835.6 → 345,836
        $this->assertSame(345_836.0, (float) $payslip->tax_amount);
        $this->assertSame(1_133_616.0, (float) $payslip->net_salary);

        // Deductions and net must agree with gross, or the payslip contradicts
        // itself in front of the person being paid.
        $this->assertSame(
            (float) $payslip->gross_salary - (float) $payslip->total_deductions,
            (float) $payslip->net_salary
        );
    }

    /** Re-running a payroll corrects the payslip rather than issuing a second one. */
    public function test_reprocessing_replaces_the_payslip(): void
    {
        $employee = $this->employee();
        $this->salary($employee, 1_200_000);
        $this->attend($employee, 30);
        $run = $this->payrollRun();

        $first = $this->payroll->processEmployee($employee, $run);
        $second = $this->payroll->processEmployee($employee, $run);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $run->payslips()->count());
    }

    /** Somebody with no salary record is paid nothing, not an error. */
    public function test_an_employee_with_no_salary_on_file_is_paid_nothing(): void
    {
        $employee = $this->employee();
        $this->attend($employee, 30);

        $payslip = $this->payroll->processEmployee($employee, $this->payrollRun());

        $this->assertSame(0.0, (float) $payslip->basic_salary);
        $this->assertSame(0.0, (float) $payslip->net_salary);
    }
}
