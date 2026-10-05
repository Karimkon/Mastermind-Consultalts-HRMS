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
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A client says how it pays its people. The engine now listens — but only so
 * far as it safely can.
 *
 * Bidco was set to "Daily (Casual)" while all 67 of its staff sat on monthly
 * 455,000. The engine read 'monthly' off the salary record, paid the monthly
 * pro-rata formula, and said nothing: the client setting was decorative, read
 * by a badge on the settings screen and by no part of payroll.
 *
 * Two rules come out of that, and they pull in opposite directions:
 *
 *  - Where the salary record states no basis, the client's payroll_type now
 *    supplies it, instead of the hardcoded 'monthly' that used to contradict
 *    the setting outright.
 *
 *  - Where the salary record DOES state one, it wins. basic_salary carries no
 *    unit of its own — salary_type is its unit — so a label that reinterprets
 *    a rate changes what the number means. UNOC is labelled 'daily' over
 *    monthly figures up to 4,488,723, and 27 of those is 121,195,521. The
 *    disagreement is reported to the account manager instead.
 *
 * The last four tests are the ones that matter: the guard against repricing a
 * rate, and the warning that replaces it.
 */
class PayrollBasisMismatchTest extends TestCase
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

    private function client(User $manager, ?string $payrollType): Client
    {
        $portal = User::create([
            'name' => 'portal',
            'email' => uniqid('p') . '@test.local',
            'password' => bcrypt('secret'),
        ]);

        return Client::create([
            'user_id' => $portal->id,
            'company_name' => 'Bidco (U) Limited',
            'contact_person' => 'Contact',
            'status' => 'active',
            'account_manager_id' => $manager->id,
            'payroll_type' => $payrollType,
        ]);
    }

    /** @param string|null $type null writes no basis at all onto the record. */
    private function employeeOf(Client $client, float $rate, ?string $type): Employee
    {
        static $n = 0;
        $n++;

        $e = Employee::create([
            'emp_number' => 'BSS-' . $n,
            'first_name' => 'Staff' . $n,
            'last_name' => 'Member',
            'hire_date' => '2020-01-01',
            'status' => 'active',
            'charge_paye' => true,
        ]);

        EmployeeSalary::create([
            'employee_id' => $e->id,
            'basic_salary' => $rate,
            // The column is NOT NULL with a default, so "states no basis" is an
            // empty string in practice rather than a null.
            'salary_type' => $type ?? '',
            'effective_from' => '2020-01-01',
            'is_current' => true,
        ]);

        $client->employees()->attach($e->id, ['assigned_by' => $client->user_id]);

        return $e;
    }

    private function runFor(Client $client): PayrollRun
    {
        return PayrollRun::create([
            'title' => 'October 2026',
            'month' => 10,
            'year' => 2026,
            'status' => 'draft',
            'client_id' => $client->id,
        ]);
    }

    private function basicFor(Employee $e, PayrollRun $run, int $days): float
    {
        PayrollManualDays::create([
            'payroll_run_id' => $run->id, 'employee_id' => $e->id, 'days_worked' => $days,
        ]);

        return (float) app(PayrollService::class)->calculatePayslip($e->fresh(), $run)['basic_salary'];
    }

    // ===== The client's type is the default where the record is silent =====

    public function test_a_daily_client_pays_daily_when_the_salary_record_states_no_basis(): void
    {
        $am = $this->accountManager('basis1@test.local');
        $client = $this->client($am, 'daily');
        $e = $this->employeeOf($client, 17500, null);

        // 17,500 x 20. The hardcoded 'monthly' this replaces gave
        // (17,500 x 12) / 365 x 20 = 11,507 for the same person.
        $this->assertSame(350000.0, $this->basicFor($e, $this->runFor($client), 20));
    }

    public function test_a_monthly_client_still_pays_monthly_when_the_record_states_no_basis(): void
    {
        $am = $this->accountManager('basis2@test.local');
        $client = $this->client($am, 'monthly');
        $e = $this->employeeOf($client, 365000, null);

        // (365,000 x 12) / 365 = 12,000 a day, x 27.
        $this->assertSame(324000.0, $this->basicFor($e, $this->runFor($client), 27));
    }

    public function test_mixed_states_no_expectation_so_the_old_monthly_default_stands(): void
    {
        $am = $this->accountManager('basis3@test.local');
        $client = $this->client($am, 'mixed');
        $e = $this->employeeOf($client, 365000, null);

        // 'mixed' says the basis varies per person, so it cannot supply one.
        $this->assertSame(324000.0, $this->basicFor($e, $this->runFor($client), 27));
    }

    public function test_the_legacy_casual_label_is_not_a_formula_and_supplies_no_basis(): void
    {
        $am = $this->accountManager('basis4@test.local');
        // 'casual' is not in the dropdown but is on a live client with 176 staff.
        $client = $this->client($am, 'casual');
        $e = $this->employeeOf($client, 365000, null);

        $this->assertSame(324000.0, $this->basicFor($e, $this->runFor($client), 27));
    }

    // ===== but it never reprices a rate that states its own basis =====

    public function test_a_daily_label_does_not_reinterpret_a_monthly_rate(): void
    {
        $am = $this->accountManager('basis5@test.local');
        $client = $this->client($am, 'daily');
        $e = $this->employeeOf($client, 365000, 'monthly');

        $basic = $this->basicFor($e, $this->runFor($client), 27);

        // Paid as the monthly rate it is: (365,000 x 12) / 365 x 27.
        $this->assertSame(324000.0, $basic);

        // And emphatically not as 27 days of 365,000, which is the whole reason
        // the label is not allowed to win.
        $this->assertNotSame(9855000.0, $basic);
    }

    public function test_a_monthly_label_does_not_reinterpret_a_daily_rate(): void
    {
        $am = $this->accountManager('basis6@test.local');
        $client = $this->client($am, 'monthly');
        $e = $this->employeeOf($client, 17500, 'daily');

        // The mirror image: a daily rate stays daily under a monthly label.
        $this->assertSame(350000.0, $this->basicFor($e, $this->runFor($client), 20));
    }

    // ===== and the account manager is told =====

    public function test_the_screen_warns_when_the_basis_contradicts_the_client_setting(): void
    {
        $am = $this->accountManager('basis7@test.local');
        $client = $this->client($am, 'daily');
        $e = $this->employeeOf($client, 455000, 'monthly');
        $run = $this->runFor($client);

        PayrollManualDays::create([
            'payroll_run_id' => $run->id, 'employee_id' => $e->id, 'days_worked' => 26,
        ]);

        $response = $this->actingAs($am)
            ->get("/account-manager/payroll/{$run->id}/select")
            ->assertOk();

        $response->assertSee('not on the pay basis', false);
        $response->assertSee('Daily (Casual)', false);
        $response->assertSee('expected daily', false);
        // The rate itself is still shown, and still as the monthly figure it is.
        $response->assertSee('455,000', false);
    }

    public function test_the_screen_says_nothing_when_the_basis_agrees(): void
    {
        $am = $this->accountManager('basis8@test.local');
        $client = $this->client($am, 'daily');
        $e = $this->employeeOf($client, 17500, 'daily');
        $run = $this->runFor($client);

        PayrollManualDays::create([
            'payroll_run_id' => $run->id, 'employee_id' => $e->id, 'days_worked' => 26,
        ]);

        $response = $this->actingAs($am)
            ->get("/account-manager/payroll/{$run->id}/select")
            ->assertOk();

        $response->assertSee('17,500', false);
        $response->assertDontSee('not on the pay basis', false);
        $response->assertDontSee('expected daily', false);
    }

    public function test_a_mixed_client_is_never_warned_about(): void
    {
        $am = $this->accountManager('basis9@test.local');
        $client = $this->client($am, 'mixed');
        $daily = $this->employeeOf($client, 17500, 'daily');
        $monthly = $this->employeeOf($client, 455000, 'monthly');
        $run = $this->runFor($client);

        foreach ([$daily, $monthly] as $e) {
            PayrollManualDays::create([
                'payroll_run_id' => $run->id, 'employee_id' => $e->id, 'days_worked' => 26,
            ]);
        }

        $response = $this->actingAs($am)
            ->get("/account-manager/payroll/{$run->id}/select")
            ->assertOk();

        // Both bases on one run is what 'mixed' means, not a mistake.
        $response->assertSee('17,500', false);
        $response->assertSee('455,000', false);
        $response->assertDontSee('not on the pay basis', false);
    }

    public function test_somebody_with_no_rate_is_not_also_reported_as_a_mismatch(): void
    {
        $am = $this->accountManager('basis10@test.local');
        $client = $this->client($am, 'daily');
        $e = $this->employeeOf($client, 0, 'monthly');
        $run = $this->runFor($client);

        PayrollManualDays::create([
            'payroll_run_id' => $run->id, 'employee_id' => $e->id, 'days_worked' => 26,
        ]);

        $response = $this->actingAs($am)
            ->get("/account-manager/payroll/{$run->id}/select")
            ->assertOk();

        // "no rate" is the bigger problem and is already shown. Saying both
        // would be two warnings for one broken record.
        $response->assertSee('no rate', false);
        $response->assertDontSee('not on the pay basis', false);
    }
}
