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

    private function slip(PayrollRun $run, float $paye, float $nssf, float $deductions): Payslip
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
        preg_match('/data-nssf="([\d.]+)"/', $html, $n);
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

        foreach (['foot-basic', 'foot-gross', 'foot-paye', 'foot-nssf', 'foot-other', 'foot-ded'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $html, $id . ' total is missing');
        }

        // Two identical slips, so the totals are doubled.
        $this->assertStringContainsString('98,000', $html, 'PAYE total');
        $this->assertStringContainsString('49,000', $html, 'NSSF total');
        $this->assertStringContainsString('147,000', $html, 'deductions total');
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
