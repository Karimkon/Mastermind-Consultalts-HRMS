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
 * The run page's Deductions column must be the deduction.
 *
 * It was `total_deductions - tax_amount` -- the deduction minus PAYE. With
 * PAYE and NSSF already in columns of their own that is simply NSSF printed
 * a second time, and the real total appeared nowhere: a 73,500 deduction
 * showed as -24,500 beside an NSSF of -24,500.
 *
 * It uses the same partials as the three review screens now, so the run page
 * and the approval screens cannot disagree about what was deducted. These
 * tests pin the arithmetic rather than the markup: whatever the columns are
 * called, the parts have to add up to the total and the total has to be the
 * total.
 */
class PayrollRunPageDeductionsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        foreach (['super-admin', 'employee'] as $r) Role::findOrCreate($r, 'web');

        $u = User::create(['name' => 'Admin', 'email' => uniqid('a') . '@test.local', 'password' => bcrypt('secret')]);
        $u->assignRole(['employee', 'super-admin']);

        return $u->fresh();
    }

    private function runWith(float $paye, float $nssf, float $deductions, array $components = []): PayrollRun
    {
        static $month = 0;
        $month++;

        $portal = User::create(['name' => 'p', 'email' => uniqid('p') . '@test.local', 'password' => bcrypt('secret')]);
        $client = Client::create([
            'user_id' => $portal->id, 'company_name' => 'Bidco (U) Limited',
            'contact_person' => 'C', 'status' => 'active',
        ]);

        $run = PayrollRun::create([
            'title' => 'Bidco October 2026', 'month' => $month, 'year' => 2026,
            'status' => 'processed', 'client_id' => $client->id,
        ]);

        static $n = 0;
        $n++;
        $e = Employee::create([
            'emp_number' => 'RP-' . $n, 'first_name' => 'Jumah', 'last_name' => 'Kayondo',
            'hire_date' => '2020-01-01', 'status' => 'active',
        ]);

        Payslip::create([
            'payroll_run_id' => $run->id, 'employee_id' => $e->id,
            'basic_salary' => 490000, 'total_allowances' => 0, 'gross_salary' => 490000,
            'tax_amount' => $paye, 'employee_nssf' => $nssf, 'employer_nssf' => $nssf * 2,
            'total_deductions' => $deductions, 'net_salary' => 490000 - $deductions,
            'worked_days' => 28, 'absent_days' => 4,
            'component_details' => $components,
        ]);

        return $run;
    }

    public function test_the_deductions_column_shows_the_whole_deduction(): void
    {
        $run = $this->runWith(paye: 49000, nssf: 24500, deductions: 73500);

        $html = $this->actingAs($this->admin())->get("/payroll/{$run->id}")->assertOk()->getContent();

        // The figure that was missing from the page entirely.
        $this->assertStringContainsString('73,500', $html, 'the real deduction total is not shown');
    }

    public function test_the_deduction_is_not_the_total_less_paye(): void
    {
        // 90,000 - 49,000 = 41,000, which is what the old column would print
        // and is neither the deduction nor any part of it.
        $run = $this->runWith(paye: 49000, nssf: 24500, deductions: 90000, components: [
            ['name' => 'Salary advance', 'code' => 'ADVANCE', 'type' => 'deduction', 'amount' => 16500],
        ]);

        $html = $this->actingAs($this->admin())->get("/payroll/{$run->id}")->assertOk()->getContent();

        $this->assertStringContainsString('90,000', $html, 'the total must be shown');
        $this->assertStringNotContainsString('41,000', $html, 'total-less-PAYE must not be shown as the deduction');
    }

    public function test_the_parts_on_the_run_page_add_up_to_the_total(): void
    {
        $run = $this->runWith(paye: 49000, nssf: 24500, deductions: 90000, components: [
            ['name' => 'Salary advance', 'code' => 'ADVANCE', 'type' => 'deduction', 'amount' => 16500],
        ]);

        $slip = $run->payslips()->first();

        $this->assertSame(49000.0, $slip->deductionPart('PAYE'));
        $this->assertSame(24500.0, $slip->deductionPart('NSSF_EMP'));
        $this->assertSame(16500.0, $slip->otherDeductions(), 'the advance lands in Other');

        $this->assertEqualsWithDelta(
            (float) $slip->total_deductions,
            $slip->deductionPart('PAYE') + $slip->deductionPart('NSSF_EMP') + $slip->otherDeductions(),
            0.5,
            'the parts must reconcile to the total'
        );
    }

    public function test_nssf_is_read_from_the_component_when_the_column_is_empty(): void
    {
        // An imported run can carry the line and no column. employeeNssf()
        // already handled that; deductionPart() now does too, so the run page
        // and the review screens agree on an imported run as well.
        $run = $this->runWith(paye: 49000, nssf: 0, deductions: 73500, components: [
            ['name' => 'NSSF (Employee 5%)', 'code' => 'NSSF_EMP', 'type' => 'deduction', 'amount' => 24500],
        ]);

        $slip = $run->payslips()->first();

        $this->assertSame(24500.0, $slip->deductionPart('NSSF_EMP'));
        $this->assertSame(0.0, $slip->otherDeductions(), 'it must not also be counted as Other');
    }

    public function test_paye_is_read_from_the_component_when_the_column_is_empty(): void
    {
        $run = $this->runWith(paye: 0, nssf: 24500, deductions: 73500, components: [
            ['name' => 'PAYE (Income Tax)', 'code' => 'PAYE', 'type' => 'deduction', 'amount' => 49000],
        ]);

        $slip = $run->payslips()->first();

        $this->assertSame(49000.0, $slip->deductionPart('PAYE'));
        $this->assertSame(0.0, $slip->otherDeductions());
    }

    public function test_the_run_page_shows_the_same_columns_as_the_review_screens(): void
    {
        $run = $this->runWith(paye: 49000, nssf: 24500, deductions: 73500);

        $html = $this->actingAs($this->admin())->get("/payroll/{$run->id}")->assertOk()->getContent();

        foreach (['PAYE', 'NSSF 5%', 'Other', 'Deductions'] as $column) {
            $this->assertStringContainsString('>' . $column . '<', $html,
                'the run page is missing the ' . $column . ' column');
        }
    }
}
