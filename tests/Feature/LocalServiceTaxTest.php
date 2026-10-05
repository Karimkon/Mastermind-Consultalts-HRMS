<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\PayrollManualDays;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Local Service Tax.
 *
 * charge_lst sat on 1,246 of 1,247 employee records and on the Statutory tab
 * of Employee Central, and PayrollService never read it. Not one shilling of
 * LST has ever been deducted by this system — the same shape of defect as the
 * NSSF tickbox that charged nothing.
 *
 * LST is one ANNUAL charge, banded on monthly income, collected in equal
 * instalments over the first months of the financial year. The two things
 * that make it easy to get wrong are both pinned here: nothing is taken in
 * the other eight months, and the band is an annual figure that must be
 * divided, not deducted whole.
 *
 * The bands themselves are config, not code. These tests assert the
 * behaviour against the configured schedule rather than hard-coding the
 * national one, so correcting a band does not break the suite.
 */
class LocalServiceTaxTest extends TestCase
{
    use RefreshDatabase;

    private function employee(float $rate, array $flags = []): Employee
    {
        static $n = 0;
        $n++;

        $e = Employee::create(array_merge([
            'emp_number' => 'LST-' . $n,
            'first_name' => 'Staff' . $n,
            'last_name'  => 'Member',
            'hire_date'  => '2020-01-01',
            'status'     => 'active',
            'charge_lst' => true,
            'charge_paye' => false,
            'charge_nssf' => false,
        ], $flags));

        EmployeeSalary::create([
            'employee_id' => $e->id, 'basic_salary' => $rate, 'salary_type' => 'monthly',
            'effective_from' => '2020-01-01', 'is_current' => true,
        ]);

        return $e;
    }

    private function runFor(int $month): PayrollRun
    {
        static $year = 2026;
        $portal = User::create(['name' => 'p', 'email' => uniqid('p') . '@test.local', 'password' => bcrypt('secret')]);
        $client = Client::create([
            'user_id' => $portal->id, 'company_name' => 'Bidco (U) Limited',
            'contact_person' => 'C', 'status' => 'active',
        ]);

        return PayrollRun::create([
            'title' => 'Run', 'month' => $month, 'year' => $year++,
            'status' => 'draft', 'client_id' => $client->id,
        ]);
    }

    /** The whole month worked, so gross is the monthly rate. */
    private function slipFor(Employee $e, PayrollRun $run): array
    {
        PayrollManualDays::create([
            'payroll_run_id' => $run->id, 'employee_id' => $e->id,
            'days_worked' => now()->setDate($run->year, $run->month, 1)->daysInMonth,
        ]);

        return app(PayrollService::class)->calculatePayslip($e->fresh(), $run);
    }

    private function lstOn(array $figures): float
    {
        foreach ($figures['component_details'] as $line) {
            if (($line['code'] ?? '') === 'LST') return (float) $line['amount'];
        }

        return 0.0;
    }

    /** The annual charge the configured schedule gives for a monthly income. */
    private function annualFor(float $gross): float
    {
        foreach (config('payroll.lst.bands') as $band) {
            if ($band['up_to'] === null || $gross <= $band['up_to']) return (float) $band['annual'];
        }

        return 0.0;
    }

    // ===== it is charged, at last =====

    public function test_lst_is_deducted_in_a_collection_month(): void
    {
        $months = config('payroll.lst.months');
        $e = $this->employee(600000);

        $figures = $this->slipFor($e, $this->runFor($months[0]));

        // From the gross the engine actually produced, not the nominal rate:
        // a monthly salary run over a 31-day month grosses more than the
        // monthly figure (annual / 365 x days), which can land the band
        // higher than the rate alone would suggest.
        $expected = round($this->annualFor((float) $figures["gross_salary"]) / count($months), 0);

        $this->assertGreaterThan(0, $expected, 'the configured schedule must charge this band');
        $this->assertSame($expected, $this->lstOn($figures));
    }

    public function test_the_instalment_is_the_annual_charge_split_over_the_collection_months(): void
    {
        $months = config('payroll.lst.months');
        $e = $this->employee(600000);

        $figures = $this->slipFor($e, $this->runFor($months[0]));

        // The trap: deducting the annual figure whole, every collection month.
        $annual = $this->annualFor((float) $figures["gross_salary"]);
        $this->assertNotSame($annual, $this->lstOn($figures), 'the annual charge must be split, not taken whole');
        $this->assertEqualsWithDelta($annual, $this->lstOn($figures) * count($months), 1.0,
            'the instalments must add up to the annual charge');
    }

    public function test_nothing_is_taken_outside_the_collection_months(): void
    {
        $outside = collect(range(1, 12))->diff(config('payroll.lst.months'))->first();
        $e = $this->employee(600000);

        $figures = $this->slipFor($e, $this->runFor($outside));

        $this->assertSame(0.0, $this->lstOn($figures), "month {$outside} is not a collection month");
    }

    public function test_a_higher_earner_pays_a_higher_band(): void
    {
        $month = config('payroll.lst.months')[0];

        $low  = $this->lstOn($this->slipFor($this->employee(250000),  $this->runFor($month)));
        $high = $this->lstOn($this->slipFor($this->employee(2000000), $this->runFor($month)));

        $this->assertGreaterThan($low, $high);
    }

    public function test_the_top_band_has_no_ceiling(): void
    {
        $month  = config('payroll.lst.months')[0];
        $months = config('payroll.lst.months');

        // Well past the last banded figure: it must still be charged, at the
        // top rate, rather than falling through to nothing.
        $figures = $this->slipFor($this->employee(50000000), $this->runFor($month));

        $top = (float) collect(config('payroll.lst.bands'))->last()['annual'];
        $this->assertSame(round($top / count($months), 0), $this->lstOn($figures));
    }

    // ===== and only where it should be =====

    public function test_an_employee_not_flagged_is_not_charged(): void
    {
        $month = config('payroll.lst.months')[0];
        $e = $this->employee(600000, ['charge_lst' => false]);

        $this->assertSame(0.0, $this->lstOn($this->slipFor($e, $this->runFor($month))));
    }

    public function test_it_can_be_switched_off_system_wide(): void
    {
        config(['payroll.lst.enabled' => false]);

        $month = config('payroll.lst.months')[0];
        $this->assertSame(0.0, $this->lstOn($this->slipFor($this->employee(600000), $this->runFor($month))));
    }

    public function test_when_the_employer_carries_it_the_employee_still_takes_home_the_same(): void
    {
        $month = config('payroll.lst.months')[0];

        $staff    = $this->employee(600000);
        $carried  = $this->employee(600000, ['lst_paid_by_employer' => true]);

        $theirs   = $this->slipFor($staff,   $this->runFor($month));
        $employer = $this->slipFor($carried, $this->runFor($month));

        // Shown and owed either way, but only one of them is out of pocket.
        $this->assertGreaterThan(0, $this->lstOn($employer), 'it is still recorded');
        $this->assertSame($this->lstOn($theirs), $this->lstOn($employer));

        $this->assertLessThan(
            (float) $employer['net_salary'],
            (float) $theirs['net_salary'],
            'the employee who pays it themselves takes home less'
        );
        $this->assertSame(0.0, (float) $employer['total_deductions']);
    }

    // ===== and it reconciles =====

    public function test_lst_lands_in_the_deduction_total_and_not_in_other(): void
    {
        $month = config('payroll.lst.months')[0];
        $e = $this->employee(600000);
        $run = $this->runFor($month);

        $figures = $this->slipFor($e, $run);
        $slip = app(PayrollService::class)->processEmployee($e->fresh(), $run);

        $this->assertSame($this->lstOn($figures), $slip->deductionPart('LST'));
        $this->assertSame(0.0, $slip->otherDeductions(), 'LST has its own column, so Other stays empty');
        $this->assertEqualsWithDelta(
            (float) $slip->total_deductions,
            $slip->deductionPart('PAYE') + $slip->deductionPart('NSSF_EMP')
                + $slip->deductionPart('WHT') + $slip->deductionPart('LST'),
            0.5
        );
    }

    public function test_the_review_screens_grow_an_lst_column_once_it_is_charged(): void
    {
        $month = config('payroll.lst.months')[0];
        $e = $this->employee(600000);
        $run = $this->runFor($month);

        $this->slipFor($e, $run);
        app(PayrollService::class)->processEmployee($e->fresh(), $run);

        $parts = \App\Models\Payslip::partsInUse($run->payslips()->get());

        $this->assertArrayHasKey('LST', $parts, 'the column appears by itself once LST is charged');
    }
}
