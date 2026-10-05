<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\PayrollManualDays;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The costing shown before a run is processed must be what the run produces.
 *
 * The account manager now sees days, rate, gross, NSSF, PAYE and net against
 * each person before submitting to HR. Those figures come from
 * PayrollService::calculatePayslip() — the same code that produces the real
 * payslip, called without saving one.
 *
 * The test that matters is the last one: preview and payslip must agree to the
 * shilling. A preview that disagrees with the payslip is worse than no preview,
 * because it is believed.
 */
class PayrollCostingPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function accountManager(string $email): User
    {
        Role::findOrCreate('account-manager', 'web');
        Role::findOrCreate('employee', 'web');

        $u = User::create(['name' => 'Account Manager', 'email' => $email, 'password' => bcrypt('secret')]);
        $u->assignRole(['employee', 'account-manager']);

        return $u->fresh();
    }

    private function client(User $manager): Client
    {
        $portal = User::create([
            'name' => 'portal',
            'email' => uniqid('p') . '@test.local',
            'password' => bcrypt('secret'),
        ]);

        return Client::create([
            'user_id' => $portal->id,
            'company_name' => 'Roofings Uganda Limited',
            'contact_person' => 'Contact',
            'status' => 'active',
            'account_manager_id' => $manager->id,
        ]);
    }

    private function employeeOf(Client $client, float $rate = 600000, string $type = 'monthly'): Employee
    {
        static $n = 0;
        $n++;

        $e = Employee::create([
            'emp_number' => 'CST-' . $n,
            'first_name' => 'Staff' . $n,
            'last_name' => 'Member',
            'hire_date' => '2020-01-01',
            'status' => 'active',
            'charge_paye' => true,
        ]);

        EmployeeSalary::create([
            'employee_id' => $e->id,
            'basic_salary' => $rate,
            'salary_type' => $type,
            'effective_from' => '2020-01-01',
            'is_current' => true,
        ]);

        $client->employees()->attach($e->id, ['assigned_by' => $client->user_id]);

        return $e;
    }

    private function payrollRun(Client $client): PayrollRun
    {
        return PayrollRun::create([
            'title' => 'October 2026',
            'month' => 10,
            'year' => 2026,
            'status' => 'draft',
            'client_id' => $client->id,
        ]);
    }

    // ===== The columns are there, with real numbers =====

    public function test_the_screen_shows_the_costing_columns(): void
    {
        $am = $this->accountManager('cost1@test.local');
        $client = $this->client($am);
        $e = $this->employeeOf($client);
        $run = $this->payrollRun($client);

        PayrollManualDays::create([
            'payroll_run_id' => $run->id, 'employee_id' => $e->id, 'days_worked' => 20,
        ]);

        $response = $this->actingAs($am)
            ->get("/account-manager/payroll/{$run->id}/select")
            ->assertOk();

        foreach (['Days worked', 'Rate', 'Gross', 'NSSF', 'PAYE', 'Net pay'] as $heading) {
            $response->assertSee($heading, false);
        }
    }

    public function test_a_selected_employee_is_costed_and_an_unselected_one_is_not(): void
    {
        $am = $this->accountManager('cost2@test.local');
        $client = $this->client($am);
        $selected = $this->employeeOf($client);
        $idle = $this->employeeOf($client);
        $run = $this->payrollRun($client);

        PayrollManualDays::create([
            'payroll_run_id' => $run->id, 'employee_id' => $selected->id, 'days_worked' => 30,
        ]);

        $html = $this->actingAs($am)
            ->get("/account-manager/payroll/{$run->id}/select")
            ->assertOk()
            ->getContent();

        // The costed row carries figures; the other carries an em dash.
        $this->assertStringContainsString('data-cost="net" data-employee="' . $selected->id . '"', $html);
        $this->assertStringContainsString('data-cost="net" data-employee="' . $idle->id . '"', $html);

        $selectedCell = $this->cellFor($html, 'net', $selected->id);
        $idleCell = $this->cellFor($html, 'net', $idle->id);

        $this->assertNotSame('—', trim($selectedCell), 'A selected employee was not costed.');
        $this->assertSame('—', trim($idleCell), 'An unselected employee was costed anyway.');
    }

    private function cellFor(string $html, string $kind, int $employeeId): string
    {
        $needle = 'data-cost="' . $kind . '" data-employee="' . $employeeId . '">';
        $start = strpos($html, $needle);
        if ($start === false) {
            return '';
        }
        $start += strlen($needle);

        return html_entity_decode(trim(substr($html, $start, strpos($html, '</td>', $start) - $start)));
    }

    // ===== Autosave returns the recalculated figures =====

    public function test_autosave_returns_the_figures_so_the_columns_follow_the_days(): void
    {
        $am = $this->accountManager('cost3@test.local');
        $client = $this->client($am);
        $e = $this->employeeOf($client);
        $run = $this->payrollRun($client);

        $response = $this->actingAs($am)
            ->postJson("/account-manager/payroll/{$run->id}/select", [
                'include' => [$e->id],
                'days' => [$e->id => 30],
            ])
            ->assertOk();

        $response->assertJsonStructure([
            'preview' => [
                'rows' => [$e->id => ['days', 'gross', 'nssf', 'paye', 'net']],
                'totals' => ['gross', 'nssf', 'paye', 'net', 'count'],
            ],
        ]);

        $this->assertGreaterThan(0, $response->json("preview.rows.{$e->id}.gross"));
        $this->assertSame(1, $response->json('preview.totals.count'));
    }

    public function test_the_totals_add_up_across_everybody_selected(): void
    {
        $am = $this->accountManager('cost4@test.local');
        $client = $this->client($am);
        $a = $this->employeeOf($client);
        $b = $this->employeeOf($client);
        $run = $this->payrollRun($client);

        $body = $this->actingAs($am)
            ->postJson("/account-manager/payroll/{$run->id}/select", [
                'include' => [$a->id, $b->id],
                'days' => [$a->id => 30, $b->id => 15],
            ])
            ->assertOk()
            ->json('preview');

        $this->assertSame(2, $body['totals']['count']);
        $this->assertEqualsWithDelta(
            $body['rows'][$a->id]['net'] + $body['rows'][$b->id]['net'],
            $body['totals']['net'],
            1.0,
            'The net total does not match the rows it is made of.'
        );
    }

    // ===== The one that matters =====

    /** Preview and payslip must agree to the shilling. */
    public function test_the_preview_matches_the_payslip_the_run_produces(): void
    {
        $am = $this->accountManager('cost5@test.local');
        $client = $this->client($am);
        $a = $this->employeeOf($client, 800000, 'monthly');
        $b = $this->employeeOf($client, 16615, 'daily');
        $run = $this->payrollRun($client);

        $preview = $this->actingAs($am)
            ->postJson("/account-manager/payroll/{$run->id}/select", [
                'include' => [$a->id, $b->id],
                'days' => [$a->id => 30, $b->id => 18],
            ])
            ->assertOk()
            ->json('preview.rows');

        // Now actually process the run.
        app(PayrollService::class)->processRun($run);

        foreach ([$a, $b] as $employee) {
            $slip = Payslip::where('payroll_run_id', $run->id)
                ->where('employee_id', $employee->id)->firstOrFail();

            $shown = $preview[$employee->id];

            $this->assertEqualsWithDelta($slip->gross_salary, $shown['gross'], 1.0,
                "Gross shown for {$employee->emp_number} differs from the payslip.");
            $this->assertEqualsWithDelta($slip->employee_nssf, $shown['nssf'], 1.0,
                "NSSF shown for {$employee->emp_number} differs from the payslip.");
            $this->assertEqualsWithDelta($slip->tax_amount, $shown['paye'], 1.0,
                "PAYE shown for {$employee->emp_number} differs from the payslip.");
            $this->assertEqualsWithDelta($slip->net_salary, $shown['net'], 1.0,
                "Net shown for {$employee->emp_number} differs from the payslip.");
        }
    }

    /** Previewing must not create payslips. */
    public function test_previewing_writes_no_payslips(): void
    {
        $am = $this->accountManager('cost6@test.local');
        $client = $this->client($am);
        $e = $this->employeeOf($client);
        $run = $this->payrollRun($client);

        $this->actingAs($am)->postJson("/account-manager/payroll/{$run->id}/select", [
            'include' => [$e->id],
            'days' => [$e->id => 30],
        ])->assertOk();

        $this->actingAs($am)->get("/account-manager/payroll/{$run->id}/select")->assertOk();

        $this->assertSame(0, Payslip::where('payroll_run_id', $run->id)->count(),
            'Looking at the costing created payslips.');
        $this->assertSame('draft', $run->fresh()->status);
    }
}
