<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\PayrollManualDays;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The screen where an account manager picks who is on a payroll run.
 *
 * Two things are being protected here.
 *
 * The gate: an account manager may only touch runs for clients they manage.
 * This system has duplicate user accounts for the same person — "Brenda
 * Kansiime" id 18 manages nothing while "Kansiime Brenda" id 808 manages
 * Roofings — so the check has to be on what the account actually manages, never
 * on a name or on holding the role.
 *
 * And autosave: 439 employees is an afternoon's work, and it must survive a
 * closed laptop. Autosave posts to the same endpoint as the Save button, which
 * answers with a receipt instead of a redirect when asked for JSON, so the two
 * cannot drift apart.
 */
class PayrollSelectionScreenTest extends TestCase
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

    private function client(User $manager, string $name = 'Roofings Uganda Limited'): Client
    {
        $portal = User::create([
            'name' => $name . ' portal',
            'email' => \Illuminate\Support\Str::slug($name) . uniqid() . '@test.local',
            'password' => bcrypt('secret'),
        ]);

        return Client::create([
            'user_id' => $portal->id,
            'company_name' => $name,
            'contact_person' => 'Contact',
            'status' => 'active',
            'account_manager_id' => $manager->id,
        ]);
    }

    private function employeeOf(Client $client, string $name): Employee
    {
        static $n = 0;
        $n++;

        $e = Employee::create([
            'emp_number' => 'SCR-' . $n,
            'first_name' => $name,
            'last_name' => 'Worker',
            'hire_date' => '2020-01-01',
            'status' => 'active',
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

    // ===== The screen =====

    public function test_the_managing_account_manager_sees_their_employees(): void
    {
        $am = $this->accountManager('am@test.local');
        $client = $this->client($am);
        $this->employeeOf($client, 'Sande');
        $this->employeeOf($client, 'Ashiraf');
        $run = $this->payrollRun($client);

        $response = $this->actingAs($am)
            ->get("/account-manager/payroll/{$run->id}/select")
            ->assertOk();

        $response->assertSee('Sande', false);
        $response->assertSee('Ashiraf', false);
        $response->assertSee('Select all', false);
        $response->assertSee('Clear all', false);
        $response->assertSee('Days worked', false);
    }

    /** The duplicate-account trap: holding the role is not managing the client. */
    public function test_an_account_manager_who_does_not_manage_the_client_is_refused(): void
    {
        $owner = $this->accountManager('owner@test.local');
        $other = $this->accountManager('other@test.local');
        $client = $this->client($owner);
        $this->employeeOf($client, 'Sande');
        $run = $this->payrollRun($client);

        $this->actingAs($other)
            ->get("/account-manager/payroll/{$run->id}/select")
            ->assertForbidden();
    }

    // ===== Saving =====

    public function test_saving_records_only_the_ticked_employees(): void
    {
        $am = $this->accountManager('am2@test.local');
        $client = $this->client($am);
        $worked = $this->employeeOf($client, 'Sande');
        $idle = $this->employeeOf($client, 'Ashiraf');
        $run = $this->payrollRun($client);

        $this->actingAs($am)->post("/account-manager/payroll/{$run->id}/select", [
            'include' => [$worked->id],
            'days' => [$worked->id => 15, $idle->id => 20],   // days for someone unticked
        ])->assertRedirect();

        $rows = PayrollManualDays::where('payroll_run_id', $run->id)->get();

        $this->assertCount(1, $rows, 'An unticked employee was saved anyway.');
        $this->assertSame($worked->id, $rows->first()->employee_id);
        $this->assertSame(15, (int) $rows->first()->days_worked);
    }

    public function test_unticking_somebody_removes_them_from_the_run(): void
    {
        $am = $this->accountManager('am3@test.local');
        $client = $this->client($am);
        $a = $this->employeeOf($client, 'Sande');
        $b = $this->employeeOf($client, 'Ashiraf');
        $run = $this->payrollRun($client);

        PayrollManualDays::create(['payroll_run_id' => $run->id, 'employee_id' => $a->id, 'days_worked' => 15]);
        PayrollManualDays::create(['payroll_run_id' => $run->id, 'employee_id' => $b->id, 'days_worked' => 10]);

        // Save again with only one of them ticked.
        $this->actingAs($am)->post("/account-manager/payroll/{$run->id}/select", [
            'include' => [$a->id],
            'days' => [$a->id => 15],
        ])->assertRedirect();

        $this->assertSame(1, PayrollManualDays::where('payroll_run_id', $run->id)->count());
        $this->assertNull(PayrollManualDays::where('payroll_run_id', $run->id)
            ->where('employee_id', $b->id)->first());
    }

    /** Somebody else's employee cannot be posted onto the run. */
    public function test_an_employee_outside_the_run_is_ignored(): void
    {
        $am = $this->accountManager('am4@test.local');
        $client = $this->client($am);
        $mine = $this->employeeOf($client, 'Sande');
        $run = $this->payrollRun($client);

        $otherAm = $this->accountManager('other4@test.local');
        $otherClient = $this->client($otherAm, 'Someone Else Ltd');
        $theirs = $this->employeeOf($otherClient, 'Stranger');

        $this->actingAs($am)->post("/account-manager/payroll/{$run->id}/select", [
            'include' => [$mine->id, $theirs->id],
            'days' => [$mine->id => 15, $theirs->id => 15],
        ])->assertRedirect();

        $saved = PayrollManualDays::where('payroll_run_id', $run->id)->pluck('employee_id');
        $this->assertTrue($saved->contains($mine->id));
        $this->assertFalse($saved->contains($theirs->id),
            "An employee from another manager's client was added to the run.");
    }

    // ===== Autosave =====

    public function test_autosave_gets_a_receipt_rather_than_a_redirect(): void
    {
        $am = $this->accountManager('am5@test.local');
        $client = $this->client($am);
        $e = $this->employeeOf($client, 'Sande');
        $run = $this->payrollRun($client);

        $this->actingAs($am)
            ->postJson("/account-manager/payroll/{$run->id}/select", [
                'include' => [$e->id],
                'days' => [$e->id => 12],
            ])
            ->assertOk()
            ->assertJsonStructure(['selected', 'saved_at'])
            ->assertJsonPath('selected', 1);

        $this->assertSame(12, (int) PayrollManualDays::where('payroll_run_id', $run->id)
            ->where('employee_id', $e->id)->first()->days_worked);
    }

    public function test_days_beyond_the_month_are_refused(): void
    {
        $am = $this->accountManager('am6@test.local');
        $client = $this->client($am);
        $e = $this->employeeOf($client, 'Sande');
        $run = $this->payrollRun($client);     // October, 31 days

        $this->actingAs($am)->post("/account-manager/payroll/{$run->id}/select", [
            'include' => [$e->id],
            'days' => [$e->id => 45],
        ])->assertSessionHasErrors();

        $this->assertSame(0, PayrollManualDays::where('payroll_run_id', $run->id)->count());
    }

    /** A run that has left the account manager's desk can no longer be edited. */
    public function test_a_run_already_with_hr_cannot_be_changed(): void
    {
        $am = $this->accountManager('am7@test.local');
        $client = $this->client($am);
        $e = $this->employeeOf($client, 'Sande');
        $run = $this->payrollRun($client);
        $run->update(['status' => 'processed']);

        $this->actingAs($am)->post("/account-manager/payroll/{$run->id}/select", [
            'include' => [$e->id],
            'days' => [$e->id => 15],
        ]);

        $this->assertSame(0, PayrollManualDays::where('payroll_run_id', $run->id)->count());
    }
}
