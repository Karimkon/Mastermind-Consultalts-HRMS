<?php

namespace Tests\Feature;

use App\Exceptions\ImportedPayrollRunException;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Runs 25 and 27–30 on production hold 469 payslips worth UGX 132.9m that this
 * engine did not produce and cannot reproduce. They were written straight into
 * the database on 2026-09-21 by a script in neither the repository nor its
 * history, and they follow rules the engine does not implement — NSSF on 70%
 * of gross on Lakeside, PAYE recorded but never withheld, no deductions at all
 * on Sheraton.
 *
 * All five sit at status 'processed', and the web controller only refused to
 * process from 'hr_approved' upwards. One click on Process would have replaced
 * the record of what people were actually paid with figures derived from
 * today's salary records. There would have been no error and no warning.
 */
class ImportedPayrollRunTest extends TestCase
{
    use RefreshDatabase;

    private function payrollRun(array $attributes = []): PayrollRun
    {
        return PayrollRun::create(array_merge([
            'title'  => 'September 2026',
            'month'  => 9,
            'year'   => 2026,
            'status' => 'draft',
        ], $attributes));
    }

    private function employee(): Employee
    {
        $employee = Employee::create([
            'emp_number' => 'EMP-1',
            'first_name' => 'Test',
            'last_name'  => 'Employee',
            'hire_date'  => '2020-01-01',
            'status'     => 'active',
        ]);

        EmployeeSalary::create([
            'employee_id'    => $employee->id,
            'basic_salary'   => 1_200_000,
            'salary_type'    => 'monthly',
            'effective_from' => '2020-01-01',
            'is_current'     => true,
        ]);

        return $employee;
    }

    private function actingAsRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::create([
            'name'     => 'Test '.$role,
            'email'    => $role.'@test.local',
            'password' => bcrypt('secret'),
        ]);
        $user->assignRole($role);

        $this->actingAs($user);

        return $user;
    }

    public function test_an_ordinary_run_is_not_imported(): void
    {
        $this->assertFalse($this->payrollRun()->isImported());
    }

    public function test_a_run_with_an_import_date_reports_itself_imported(): void
    {
        $this->assertTrue($this->payrollRun(['imported_at' => now()])->isImported());
    }

    /** The service refuses, so no caller can reach the calculation by accident. */
    public function test_the_service_refuses_to_process_an_imported_run(): void
    {
        $run = $this->payrollRun(['status' => 'processed', 'imported_at' => now()]);

        $this->expectException(ImportedPayrollRunException::class);

        app(PayrollService::class)->processRun($run);
    }

    /** And it writes no payslips on the way out. */
    public function test_refusing_leaves_the_imported_payslips_untouched(): void
    {
        $this->employee();
        $run = $this->payrollRun(['status' => 'processed', 'imported_at' => now()]);

        try {
            app(PayrollService::class)->processRun($run);
        } catch (ImportedPayrollRunException) {
            // expected
        }

        $this->assertSame(0, $run->payslips()->count());
    }

    public function test_an_ordinary_run_still_processes(): void
    {
        $this->employee();
        $run = $this->payrollRun();

        $count = app(PayrollService::class)->processRun($run);

        $this->assertSame(1, $count);
        $this->assertSame('processed', $run->fresh()->status);
    }

    /**
     * The web route is the one that was actually reachable: the five live runs
     * are at 'processed', which the old guard let straight through.
     */
    public function test_the_web_route_refuses_and_says_why(): void
    {
        $this->actingAsRole('super-admin');
        $run = $this->payrollRun(['status' => 'processed', 'imported_at' => now()]);

        $response = $this->post(route('payroll.process', $run));

        $response->assertSessionHas('error');
        $this->assertStringContainsString('imported', session('error'));
        $this->assertSame(0, $run->payslips()->count());
    }

    public function test_the_api_route_refuses_with_a_422(): void
    {
        $user = $this->actingAsRole('super-admin');
        $run  = $this->payrollRun(['status' => 'draft', 'imported_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/payroll/'.$run->id.'/process');

        $response->assertStatus(422);
        $this->assertStringContainsString('imported', $response->json('message'));
        $this->assertSame(0, $run->payslips()->count());
    }

    /** The banner has to be on the page, or nobody reading the figures knows. */
    public function test_the_run_page_says_the_payslips_were_imported(): void
    {
        $this->actingAsRole('super-admin');
        $run = $this->payrollRun([
            'status'      => 'processed',
            'imported_at' => now(),
            'import_note' => 'Loaded from the August spreadsheet.',
        ]);

        $this->get(route('payroll.show', $run))
            ->assertOk()
            ->assertSee('imported, not calculated', false)
            ->assertSee('Loaded from the August spreadsheet.', false);
    }
}
