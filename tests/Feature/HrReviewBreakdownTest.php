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
 * HR approves a run on this screen, so it has to show what is being deducted.
 *
 * Deductions arrived as a single number: 73,500 against a 490,000 basic, with
 * nothing to say how much of it was tax and how much was NSSF. HR is the
 * stage that checks the figures before they go to Finance, and that is not
 * checkable from one total.
 *
 * PAYE, NSSF and Other now sit before it and add up to it exactly. "Other"
 * is not decoration: 122 of the 2,605 live payslips carry a deduction beyond
 * the two statutory ones, the largest 333,333, so showing only PAYE and NSSF
 * would leave an unexplained gap and imply the statutory pair was the whole
 * of it.
 */
class HrReviewBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private function hrAdmin(): User
    {
        Role::findOrCreate('hr-admin', 'web');
        Role::findOrCreate('employee', 'web');

        $u = User::create(['name' => 'HR', 'email' => uniqid('hr') . '@test.local', 'password' => bcrypt('secret')]);
        $u->assignRole(['employee', 'hr-admin']);

        return $u->fresh();
    }

    private function processedRun(): PayrollRun
    {
        $portal = User::create(['name' => 'portal', 'email' => uniqid('p') . '@test.local', 'password' => bcrypt('secret')]);
        $client = Client::create([
            'user_id' => $portal->id, 'company_name' => 'Bidco (U) Limited',
            'contact_person' => 'C', 'status' => 'active',
        ]);

        return PayrollRun::create([
            'title' => 'October 2026', 'month' => 10, 'year' => 2026,
            'status' => 'processed', 'client_id' => $client->id,
        ]);
    }

    private function slip(PayrollRun $run, float $paye, float $nssf, float $deductions, array $components = []): Payslip
    {
        static $n = 0;
        $n++;

        $e = Employee::create([
            'emp_number' => 'BID' . str_pad((string) $n, 3, '0', STR_PAD_LEFT),
            'first_name' => 'Jumah' . $n, 'last_name' => 'Kayondo',
            'hire_date' => '2020-01-01', 'status' => 'active',
        ]);

        return Payslip::create([
            'payroll_run_id' => $run->id,
            'employee_id'    => $e->id,
            'basic_salary'   => 490000,
            'gross_salary'   => 490000,
            'tax_amount'     => $paye,
            'employee_nssf'  => $nssf,
            'employer_nssf'  => $nssf * 2,
            'total_deductions' => $deductions,
            'net_salary'     => 490000 - $deductions,
            'worked_days'    => 28,
            'absent_days'    => 4,
            'component_details' => $components,
        ]);
    }

    private function review(User $hr, PayrollRun $run)
    {
        return $this->actingAs($hr)->get("/payroll/{$run->id}/hr-review");
    }

    public function test_the_screen_shows_paye_and_nssf_before_deductions(): void
    {
        $hr  = $this->hrAdmin();
        $run = $this->processedRun();
        $this->slip($run, paye: 49000, nssf: 24500, deductions: 73500);

        $response = $this->review($hr, $run)->assertOk();

        foreach (['PAYE', 'NSSF 5%', 'Other', 'Deductions'] as $heading) {
            $response->assertSee($heading, false);
        }

        // The parts, and the total they make.
        $response->assertSee('49,000', false);
        $response->assertSee('24,500', false);
        $response->assertSee('73,500', false);
    }

    public function test_the_job_title_column_is_called_designation_not_role(): void
    {
        $hr  = $this->hrAdmin();
        $run = $this->processedRun();
        $this->slip($run, paye: 49000, nssf: 24500, deductions: 73500);

        $html = $this->review($hr, $run)->assertOk()->getContent();

        // The column renders designation?->title, so it has to say so. "Role"
        // already means something else here -- Roles & Permissions, which is
        // login access, not a job title -- and searching Employee Central for
        // "role" turned up only that, with no route to the field behind it.
        $this->assertStringContainsString('>Designation<', $html);
        $this->assertStringNotContainsString('>Role<', $html);
    }

    public function test_the_columns_are_in_the_order_they_add_up_in(): void
    {
        $hr  = $this->hrAdmin();
        $run = $this->processedRun();
        $this->slip($run, paye: 49000, nssf: 24500, deductions: 73500);

        $html = $this->review($hr, $run)->assertOk()->getContent();

        $paye = strpos($html, '>PAYE<');
        $nssf = strpos($html, '>NSSF 5%<');
        $other = strpos($html, '>Other<');
        $ded  = strpos($html, '>Deductions<');

        $this->assertNotFalse($paye);
        $this->assertTrue($paye < $nssf, 'PAYE comes before NSSF');
        $this->assertTrue($nssf < $other, 'NSSF comes before Other');
        $this->assertTrue($other < $ded, 'the parts come before the total');
    }

    public function test_a_deduction_beyond_the_statutory_two_is_shown_as_other(): void
    {
        $hr  = $this->hrAdmin();
        $run = $this->processedRun();
        // 73,500 statutory plus a 100,000 advance recovered.
        $this->slip($run, paye: 49000, nssf: 24500, deductions: 173500);

        $response = $this->review($hr, $run)->assertOk();

        $response->assertSee('100,000', false);
        $response->assertSee('173,500', false);
    }

    public function test_the_row_data_reconciles_paye_plus_nssf_plus_other_to_deductions(): void
    {
        $hr  = $this->hrAdmin();
        $run = $this->processedRun();
        $this->slip($run, paye: 49000, nssf: 24500, deductions: 173500);

        $html = $this->review($hr, $run)->assertOk()->getContent();

        preg_match('/data-paye="([\d.]+)"/', $html, $p);
        preg_match('/data-nssfemp="([\d.]+)"/', $html, $n);
        preg_match('/data-other="(-?[\d.]+)"/', $html, $o);
        preg_match('/data-ded="([\d.]+)"/', $html, $d);

        $this->assertNotEmpty($p, 'the row carries its PAYE for the totals');
        $this->assertEqualsWithDelta(
            (float) $d[1],
            (float) $p[1] + (float) $n[1] + (float) $o[1],
            0.5,
            'the parts must add up to the total'
        );
    }

    public function test_every_money_column_is_totalled_in_the_footer(): void
    {
        $hr  = $this->hrAdmin();
        $run = $this->processedRun();
        $this->slip($run, paye: 49000, nssf: 24500, deductions: 73500);
        $this->slip($run, paye: 49000, nssf: 24500, deductions: 73500);

        $html = $this->review($hr, $run)->assertOk()->getContent();

        foreach (['foot-basic', 'foot-gross', 'foot-paye', 'foot-nssfemp', 'foot-other', 'foot-ded'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $html, $id . ' total is missing');
        }

        // Two identical slips, so the totals are doubled.
        $this->assertStringContainsString('98,000', $html, 'PAYE total');
        $this->assertStringContainsString('49,000', $html, 'NSSF total');
        $this->assertStringContainsString('147,000', $html, 'deductions total');
    }

    // ===== withholding tax and local service tax =====

    public function test_wht_gets_its_own_column_when_the_run_carries_it(): void
    {
        $hr  = $this->hrAdmin();
        $run = $this->processedRun();
        // WHT has no payslip column of its own -- it is a line inside
        // component_details, written by the engine at 6% of gross.
        $this->slip($run, paye: 49000, nssf: 24500, deductions: 102900, components: [
            ['name' => 'Withholding Tax (6%)', 'code' => 'WHT', 'type' => 'deduction', 'amount' => 29400],
        ]);

        $response = $this->review($hr, $run)->assertOk();

        $response->assertSee('>WHT<', false);
        $response->assertSee('29,400', false);
    }

    public function test_wht_has_no_column_on_a_run_without_it(): void
    {
        $hr  = $this->hrAdmin();
        $run = $this->processedRun();
        $this->slip($run, paye: 49000, nssf: 24500, deductions: 73500);

        // A column of dashes reads as a figure somebody checked.
        $this->review($hr, $run)->assertOk()->assertDontSee('>WHT<', false);
    }

    public function test_lst_would_get_a_column_the_moment_it_is_calculated(): void
    {
        $hr  = $this->hrAdmin();
        $run = $this->processedRun();
        // Nothing writes an LST line today -- charge_lst is ticked on 1,246
        // employees and the engine never reads it. The column is driven off
        // the component code, so it appears by itself once it does.
        $this->slip($run, paye: 49000, nssf: 24500, deductions: 83500, components: [
            ['name' => 'Local Service Tax', 'code' => 'LST', 'type' => 'deduction', 'amount' => 10000],
        ]);

        $this->review($hr, $run)->assertOk()
            ->assertSee('>LST<', false)
            ->assertSee('10,000', false);
    }

    public function test_wht_is_not_swept_into_other(): void
    {
        $hr  = $this->hrAdmin();
        $run = $this->processedRun();
        $slip = $this->slip($run, paye: 49000, nssf: 24500, deductions: 102900, components: [
            ['name' => 'Withholding Tax (6%)', 'code' => 'WHT', 'type' => 'deduction', 'amount' => 29400],
        ]);

        // 49,000 + 24,500 + 29,400 = 102,900, so Other is nothing.
        $this->assertSame(29400.0, $slip->deductionPart('WHT'));
        $this->assertSame(0.0, $slip->otherDeductions());
    }

    public function test_the_parts_still_reconcile_with_wht_and_a_further_deduction(): void
    {
        $hr  = $this->hrAdmin();
        $run = $this->processedRun();
        $slip = $this->slip($run, paye: 49000, nssf: 24500, deductions: 152900, components: [
            ['name' => 'Withholding Tax (6%)', 'code' => 'WHT', 'type' => 'deduction', 'amount' => 29400],
            ['name' => 'Salary advance', 'code' => 'ADVANCE', 'type' => 'deduction', 'amount' => 50000],
        ]);

        $named = $slip->deductionPart('PAYE') + $slip->deductionPart('NSSF_EMP')
               + $slip->deductionPart('WHT') + $slip->deductionPart('LST');

        $this->assertSame(50000.0, $slip->otherDeductions(), 'the advance lands in Other');
        $this->assertEqualsWithDelta((float) $slip->total_deductions, $named + $slip->otherDeductions(), 0.5);
    }

    public function test_an_employer_cost_line_is_not_counted_as_a_deduction(): void
    {
        $hr  = $this->hrAdmin();
        $run = $this->processedRun();
        // Employer NSSF is the company's cost, not the employee's deduction.
        $slip = $this->slip($run, paye: 49000, nssf: 24500, deductions: 73500, components: [
            ['name' => 'NSSF (Employer 10%)', 'code' => 'NSSF_CO', 'type' => 'employer_cost', 'amount' => 49000],
        ]);

        $this->assertSame(0.0, $slip->otherDeductions());
    }

    public function test_nothing_deducted_shows_a_dash_rather_than_a_zero(): void
    {
        $hr  = $this->hrAdmin();
        $run = $this->processedRun();
        $this->slip($run, paye: 0, nssf: 0, deductions: 0);

        // A column of zeroes reads as a figure that was checked; a dash does not.
        $this->review($hr, $run)->assertOk()->assertSee('—', false);
    }
}
