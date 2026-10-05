<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\PayrollManualDays;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\EmployeeSalary;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Taking somebody off payroll, and putting them back, from the payroll screen.
 *
 * The account manager preparing a run is the person who finds out that somebody
 * has left, been suspended, or should not be paid again. Sending them to another
 * screen to act on it is how a leaver stays on payroll for another month.
 *
 * Every action here is reversible from the same place, which is the point: an
 * employee who has been taken off is still listed and still searchable, because
 * somebody who has disappeared cannot be brought back.
 */
class PayrollEmployeeStatusTest extends TestCase
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
            'email' => uniqid('portal') . '@test.local',
            'password' => bcrypt('secret'),
        ]);

        return Client::create([
            'user_id' => $portal->id,
            'company_name' => 'UNOC (Uganda National Oil Company)',
            'contact_person' => 'Contact',
            'status' => 'active',
            'account_manager_id' => $manager->id,
        ]);
    }

    private function employeeOf(Client $client, string $name = 'Alex'): Employee
    {
        static $n = 0;
        $n++;

        $e = Employee::create([
            'emp_number' => 'STA-' . $n,
            'first_name' => $name,
            'last_name' => 'Mudebo',
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

    // ===== Taking somebody off =====

    public static function actions(): array
    {
        return [
            'blacklist' => ['blacklist', 'Blacklisted'],
            'hold'      => ['hold', 'On hold'],
            'suspend'   => ['suspend', 'Suspended'],
            'terminate' => ['terminate', 'Terminated'],
        ];
    }

    #[DataProvider('actions')]
    public function test_the_action_makes_them_ineligible(string $action, string $expectedReason): void
    {
        $am = $this->accountManager('am-' . $action . '@test.local');
        $client = $this->client($am);
        $e = $this->employeeOf($client);
        $run = $this->payrollRun($client);

        $this->actingAs($am)->post(
            "/account-manager/payroll/{$run->id}/employees/{$e->id}/status",
            ['action' => $action, 'reason' => 'Left the site']
        )->assertRedirect();

        $e->refresh();

        $this->assertFalse($e->isPayrollEligible(), "{$action} did not make them ineligible.");
        $this->assertSame($expectedReason, $e->payrollIneligibilityReason());
    }

    #[DataProvider('actions')]
    public function test_the_engine_will_not_pay_them(string $action): void
    {
        $am = $this->accountManager('eng-' . $action . '@test.local');
        $client = $this->client($am);
        $e = $this->employeeOf($client);
        $run = $this->payrollRun($client);

        // On the run, with days, before anything happens.
        PayrollManualDays::create([
            'payroll_run_id' => $run->id, 'employee_id' => $e->id, 'days_worked' => 20,
        ]);

        $this->actingAs($am)->post(
            "/account-manager/payroll/{$run->id}/employees/{$e->id}/status",
            ['action' => $action]
        );

        app(PayrollService::class)->processRun($run);

        $this->assertSame(0, Payslip::where('payroll_run_id', $run->id)->count(),
            "An employee who was {$action}ed was still paid.");
    }

    /** The tick goes too, or the screen keeps claiming they are on the run. */
    #[DataProvider('actions')]
    public function test_they_are_taken_off_this_run(string $action): void
    {
        $am = $this->accountManager('off-' . $action . '@test.local');
        $client = $this->client($am);
        $e = $this->employeeOf($client);
        $run = $this->payrollRun($client);

        PayrollManualDays::create([
            'payroll_run_id' => $run->id, 'employee_id' => $e->id, 'days_worked' => 20,
        ]);

        $this->actingAs($am)->post(
            "/account-manager/payroll/{$run->id}/employees/{$e->id}/status",
            ['action' => $action]
        );

        $this->assertSame(0, PayrollManualDays::where('payroll_run_id', $run->id)->count(),
            'They were taken off payroll but left ticked on the run.');
    }

    // ===== Putting them back =====

    public function test_reinstate_clears_everything_at_once(): void
    {
        $am = $this->accountManager('rein@test.local');
        $client = $this->client($am);
        $e = $this->employeeOf($client);
        $run = $this->payrollRun($client);

        // The nasty case: more than one thing set. Clearing only one of them is
        // how somebody stays mysteriously unpayable.
        $e->update([
            'is_blacklisted' => true,
            'on_hold' => true,
            'hold_end_date' => null,
            'status' => 'suspended',
        ]);
        $this->assertFalse($e->isPayrollEligible());

        $this->actingAs($am)->post(
            "/account-manager/payroll/{$run->id}/employees/{$e->id}/status",
            ['action' => 'reinstate']
        )->assertRedirect();

        $e->refresh();

        $this->assertTrue($e->isPayrollEligible(), 'Reinstating did not make them payable.');
        $this->assertFalse((bool) $e->is_blacklisted);
        $this->assertFalse((bool) $e->on_hold);
        $this->assertSame('active', $e->status);
    }

    public function test_somebody_taken_off_is_still_listed_so_they_can_be_restored(): void
    {
        $am = $this->accountManager('list@test.local');
        $client = $this->client($am);
        $e = $this->employeeOf($client, 'Alex');
        $run = $this->payrollRun($client);

        $this->actingAs($am)->post(
            "/account-manager/payroll/{$run->id}/employees/{$e->id}/status",
            ['action' => 'blacklist']
        );

        $response = $this->actingAs($am)
            ->get("/account-manager/payroll/{$run->id}/select")
            ->assertOk();

        // Visible, labelled, and with a way back — not vanished.
        $response->assertSee('Alex', false);
        $response->assertSee('Blacklisted', false);
        $response->assertSee('Make active', false);
    }

    public function test_an_ineligible_employee_cannot_be_ticked_onto_the_run(): void
    {
        $am = $this->accountManager('tick@test.local');
        $client = $this->client($am);
        $e = $this->employeeOf($client);
        $run = $this->payrollRun($client);

        $e->update(['is_blacklisted' => true]);

        // Posting them anyway, as a hand-built request would.
        $this->actingAs($am)->post("/account-manager/payroll/{$run->id}/select", [
            'include' => [$e->id],
            'days' => [$e->id => 20],
        ]);

        $this->assertSame(0, PayrollManualDays::where('payroll_run_id', $run->id)->count(),
            'A blacklisted employee was put on the run by posting directly.');
    }

    // ===== Who may do it =====

    public function test_an_account_manager_cannot_touch_somebody_elses_employee(): void
    {
        $owner = $this->accountManager('owner@test.local');
        $other = $this->accountManager('other@test.local');
        $client = $this->client($owner);
        $e = $this->employeeOf($client);
        $run = $this->payrollRun($client);

        $this->actingAs($other)->post(
            "/account-manager/payroll/{$run->id}/employees/{$e->id}/status",
            ['action' => 'blacklist']
        )->assertForbidden();

        $this->assertFalse((bool) $e->fresh()->is_blacklisted);
    }

    public function test_an_unknown_action_is_refused(): void
    {
        $am = $this->accountManager('bad@test.local');
        $client = $this->client($am);
        $e = $this->employeeOf($client);
        $run = $this->payrollRun($client);

        $this->actingAs($am)->post(
            "/account-manager/payroll/{$run->id}/employees/{$e->id}/status",
            ['action' => 'delete_everything']
        )->assertSessionHasErrors('action');
    }
}
