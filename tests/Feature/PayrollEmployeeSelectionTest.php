<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\PayrollManualDays;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Only the people chosen for a run get paid, and get a payslip.
 *
 * Roofings run 31 is the case this exists for: 439 staff on the client, six of
 * them actually on site, and the engine produced 439 payslips — 433 of them
 * zero. All 433 then went to HR to be reviewed. Zero is not a fact about
 * somebody's pay, it is the absence of one, and it buries the six that matter.
 *
 * A payroll_manual_days row IS the selection: the account manager enters days
 * for the people who worked, and that is the same list. A run with no rows
 * keeps the old behaviour — everyone eligible — so the clients who run off
 * attendance rather than manual days are untouched. That backward compatibility
 * is tested here too, because getting it wrong would stop paying people.
 */
class PayrollEmployeeSelectionTest extends TestCase
{
    use RefreshDatabase;

    private PayrollService $payroll;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payroll = app(PayrollService::class);
    }

    private function employee(string $name): Employee
    {
        static $n = 0;
        $n++;

        $e = Employee::create([
            'emp_number' => 'SEL-' . $n,
            'first_name' => $name,
            'last_name' => 'Worker',
            'hire_date' => '2020-01-01',
            'status' => 'active',
        ]);

        EmployeeSalary::create([
            'employee_id' => $e->id,
            'basic_salary' => 500000,
            'salary_type' => 'monthly',
            'effective_from' => '2020-01-01',
            'is_current' => true,
        ]);

        return $e;
    }

    private function payrollRun(): PayrollRun
    {
        return PayrollRun::create([
            'title' => 'October 2026',
            'month' => 10,
            'year' => 2026,
            'status' => 'draft',
        ]);
    }

    // ===== The bug =====

    public function test_only_the_selected_employees_get_a_payslip(): void
    {
        $worked = $this->employee('Sande');
        $this->employee('Ashiraf');     // did not work
        $this->employee('Richard');     // did not work
        $this->employee('Tonny');       // did not work

        $run = $this->payrollRun();
        PayrollManualDays::create([
            'payroll_run_id' => $run->id, 'employee_id' => $worked->id, 'days_worked' => 15,
        ]);

        $this->payroll->processRun($run);

        $slips = Payslip::where('payroll_run_id', $run->id)->get();

        $this->assertCount(1, $slips, 'Everybody was processed, not just the selected employee.');
        $this->assertSame($worked->id, $slips->first()->employee_id);
        $this->assertGreaterThan(0, (float) $slips->first()->net_salary);
    }

    public function test_the_three_who_did_not_work_get_no_zero_payslip(): void
    {
        $worked = $this->employee('Sande');
        $idle = $this->employee('Ashiraf');

        $run = $this->payrollRun();
        PayrollManualDays::create([
            'payroll_run_id' => $run->id, 'employee_id' => $worked->id, 'days_worked' => 15,
        ]);

        $this->payroll->processRun($run);

        $this->assertNull(
            Payslip::where('payroll_run_id', $run->id)->where('employee_id', $idle->id)->first(),
            'Somebody who did not work still received a payslip.'
        );
    }

    public function test_several_selected_people_are_all_paid_their_own_days(): void
    {
        $a = $this->employee('Sande');
        $b = $this->employee('Brenda');
        $this->employee('Nobody');

        $run = $this->payrollRun();
        PayrollManualDays::create(['payroll_run_id' => $run->id, 'employee_id' => $a->id, 'days_worked' => 15]);
        PayrollManualDays::create(['payroll_run_id' => $run->id, 'employee_id' => $b->id, 'days_worked' => 20]);

        $this->payroll->processRun($run);

        $slips = Payslip::where('payroll_run_id', $run->id)->get();

        $this->assertCount(2, $slips);
        $this->assertSame(15, (int) $slips->firstWhere('employee_id', $a->id)->worked_days);
        $this->assertSame(20, (int) $slips->firstWhere('employee_id', $b->id)->worked_days);

        // More days, more pay. Guards against the days being read but ignored.
        $this->assertGreaterThan(
            (float) $slips->firstWhere('employee_id', $a->id)->net_salary,
            (float) $slips->firstWhere('employee_id', $b->id)->net_salary
        );
    }

    // ===== What must not change =====

    /**
     * A run with no selection still pays everybody.
     *
     * The clients who run off attendance never touch manual days. If this broke,
     * they would simply stop being paid.
     */
    public function test_a_run_with_no_selection_still_processes_everyone(): void
    {
        $this->employee('Sande');
        $this->employee('Ashiraf');
        $this->employee('Richard');

        $run = $this->payrollRun();

        $this->payroll->processRun($run);

        $this->assertCount(3, Payslip::where('payroll_run_id', $run->id)->get(),
            'A run with no selection must behave as it always did.');
    }

    /**
     * A row of zero days is not a selection.
     *
     * The spreadsheet import writes a row for every employee on the client, so
     * Roofings run 31 held 433 rows of which only 20 carried any days. Treating
     * a row as a selection would still have paid 433 people. Somebody who worked
     * no days is not paid and needs no payslip.
     */
    public function test_zero_days_is_not_a_selection(): void
    {
        $worked = $this->employee('Sande');
        $idle = $this->employee('Ashiraf');

        $run = $this->payrollRun();
        PayrollManualDays::create([
            'payroll_run_id' => $run->id, 'employee_id' => $worked->id, 'days_worked' => 15,
        ]);
        PayrollManualDays::create([
            'payroll_run_id' => $run->id, 'employee_id' => $idle->id, 'days_worked' => 0,
        ]);

        $this->payroll->processRun($run);

        $slips = Payslip::where('payroll_run_id', $run->id)->get();
        $this->assertCount(1, $slips, 'A row of zero days still produced a payslip.');
        $this->assertSame($worked->id, $slips->first()->employee_id);
    }

    /** The shape of Roofings run 31: many rows, few with days. */
    public function test_the_roofings_case(): void
    {
        $paid = [];
        $run = $this->payrollRun();

        for ($i = 0; $i < 12; $i++) {
            $e = $this->employee('Staff' . $i);
            $days = $i < 3 ? 15 : 0;          // 3 worked, 9 did not
            if ($days > 0) { $paid[] = $e->id; }
            PayrollManualDays::create([
                'payroll_run_id' => $run->id, 'employee_id' => $e->id, 'days_worked' => $days,
            ]);
        }

        $this->payroll->processRun($run);

        $slips = Payslip::where('payroll_run_id', $run->id)->get();
        $this->assertCount(3, $slips, '12 rows of manual days produced more than the 3 who worked.');
        $this->assertEqualsCanonicalizing($paid, $slips->pluck('employee_id')->all());
        $this->assertSame(0, $slips->where('net_salary', 0)->count(), 'A payslip of zero was produced.');
    }

    public function test_a_selected_employee_who_is_not_eligible_is_still_excluded(): void
    {
        // Selection narrows the list; it does not override the rules about who
        // may be paid at all.
        $blacklisted = $this->employee('Blacklisted');
        $blacklisted->update(['is_blacklisted' => true]);

        $ok = $this->employee('Sande');

        $run = $this->payrollRun();
        foreach ([$blacklisted, $ok] as $e) {
            PayrollManualDays::create([
                'payroll_run_id' => $run->id, 'employee_id' => $e->id, 'days_worked' => 15,
            ]);
        }

        $this->payroll->processRun($run);

        $slips = Payslip::where('payroll_run_id', $run->id)->get();
        $this->assertCount(1, $slips, 'A blacklisted employee was paid because they were selected.');
        $this->assertSame($ok->id, $slips->first()->employee_id);
    }
}
