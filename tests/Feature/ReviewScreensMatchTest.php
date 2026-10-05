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
 * HR, Finance and MD approve the same payroll, so they see the same table.
 *
 * The three screens were separate copies of the same markup and had drifted:
 * HR carried a total under every money column while Finance and MD had only
 * a net total, so the two stages that sign off on the money could not see
 * what the PAYE or NSSF bill came to for the people they were approving. HR
 * also had no Status column, so it could not see who an earlier attempt had
 * withheld.
 *
 * The table and its behaviour are one partial now. These tests exist to keep
 * them one: if a column is added for one approver and not the others, the
 * first test fails.
 */
class ReviewScreensMatchTest extends TestCase
{
    use RefreshDatabase;

    /** The column headings every approver must see, in order. */
    private const COLUMNS = [
        'Employee', 'Department', 'Designation', 'Basic', 'Gross',
        'PAYE', 'NSSF 5%', 'Other', 'Deductions', 'Net Pay', 'Days', 'Status',
    ];

    private function userWith(string $role): User
    {
        foreach (['super-admin', 'hr-admin', 'payroll-officer', 'md', 'employee'] as $r) {
            Role::findOrCreate($r, 'web');
        }

        $u = User::create([
            'name' => ucfirst($role), 'email' => uniqid('u') . '@test.local', 'password' => bcrypt('secret'),
        ]);
        $u->assignRole(['employee', $role]);

        return $u->fresh();
    }

    /**
     * payroll_runs carries unique(month, year) in the migration, so each run
     * a test makes needs its own month. Production does not enforce it --
     * Roofings, UNOC and Bidco all have an October 2026 run -- so the index
     * is in the schema and not in the database it runs against.
     */
    private function runAt(string $status): PayrollRun
    {
        static $month = 0;
        $month++;
        $portal = User::create(['name' => 'p', 'email' => uniqid('p') . '@test.local', 'password' => bcrypt('secret')]);
        $client = Client::create([
            'user_id' => $portal->id, 'company_name' => 'Bidco (U) Limited',
            'contact_person' => 'C', 'status' => 'active',
        ]);

        $run = PayrollRun::create([
            'title' => 'October 2026', 'month' => $month, 'year' => 2026,
            'status' => $status, 'client_id' => $client->id,
        ]);

        static $n = 0;
        foreach ([0, 1] as $i) {
            $n++;
            $e = Employee::create([
                'emp_number' => 'RM-' . $n, 'first_name' => 'Staff' . $n, 'last_name' => 'Member',
                'hire_date' => '2020-01-01', 'status' => 'active',
            ]);

            Payslip::create([
                'payroll_run_id' => $run->id, 'employee_id' => $e->id,
                'basic_salary' => 490000, 'gross_salary' => 490000,
                'tax_amount' => 49000, 'employee_nssf' => 24500, 'employer_nssf' => 49000,
                'total_deductions' => 73500, 'net_salary' => 416500,
                'worked_days' => 28, 'absent_days' => 4,
                'payment_status' => 'pending',
            ]);
        }

        return $run;
    }

    /** @return array{0:User,1:PayrollRun,2:string} */
    private function stage(string $which): array
    {
        return match ($which) {
            'hr'      => [$this->userWith('hr-admin'),        $this->runAt('processed'),         'hr-review'],
            'finance' => [$this->userWith('payroll-officer'), $this->runAt('hr_approved'),       'finance-review'],
            'md'      => [$this->userWith('md'),              $this->runAt('finance_approved'),  'md-review'],
        };
    }

    private function open(string $which): string
    {
        [$user, $run, $screen] = $this->stage($which);

        return $this->actingAs($user)->get("/payroll/{$run->id}/{$screen}")->assertOk()->getContent();
    }

    // ===== the same columns, for all three =====

    /** @dataProvider approvers */
    public function test_every_approver_sees_the_same_columns(string $which): void
    {
        $html = $this->open($which);

        foreach (self::COLUMNS as $column) {
            $this->assertStringContainsString('>' . $column . '<', $html,
                $which . ' is missing the ' . $column . ' column');
        }
    }

    /** @dataProvider approvers */
    public function test_every_approver_gets_a_total_under_every_money_column(string $which): void
    {
        $html = $this->open($which);

        // Finance and MD had only a net total: the two stages signing off on
        // the money could not see what the deduction bill came to.
        foreach (['foot-basic', 'foot-gross', 'foot-nssfemp', 'foot-other', 'foot-ded'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $html,
                $which . ' is missing the ' . $id . ' total');
        }

        // Two slips at 490,000 / 49,000 / 24,500 / 73,500.
        $this->assertStringContainsString('980,000', $html, $which . ' basic total');
        $this->assertStringContainsString('147,000', $html, $which . ' deductions total');
    }

    /** @dataProvider approvers */
    public function test_every_approver_gets_the_same_figures(string $which): void
    {
        $html = $this->open($which);

        $this->assertStringContainsString('49,000', $html, $which . ' PAYE');
        $this->assertStringContainsString('24,500', $html, $which . ' NSSF');
        $this->assertStringContainsString('73,500', $html, $which . ' deductions');
        $this->assertStringContainsString('416,500', $html, $which . ' net');
    }

    /** @dataProvider approvers */
    public function test_every_approver_can_search_filter_and_re_total(string $which): void
    {
        $html = $this->open($which);

        foreach (['searchInput', 'deptFilter', 'selectAll'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $html, $which . ' is missing ' . $id);
        }

        // The shared behaviour, including the column list the totals follow.
        $this->assertStringContainsString('window.reviewTotalCols', $html, $which . ' has no total columns');
        $this->assertStringContainsString('function updateCount', $html, $which . ' cannot re-total');
    }

    public static function approvers(): array
    {
        return ['hr' => ['hr'], 'finance' => ['finance'], 'md' => ['md']];
    }

    // ===== but each stage keeps what is its own =====

    public function test_each_stage_keeps_its_own_heading_and_button(): void
    {
        $this->assertStringContainsString('Approve &amp; Submit to Finance', $this->open('hr'));
        $this->assertStringContainsString('Finance Approve', $this->open('finance'));
        $this->assertStringContainsString('MD Final Approval', $this->open('md'));
    }

    public function test_the_wrong_approver_is_refused(): void
    {
        // An HR admin has no business on the MD's final approval.
        [, $run] = $this->stage('md');
        $hr = $this->userWith('hr-admin');

        $this->actingAs($hr)->get("/payroll/{$run->id}/md-review")->assertForbidden();
    }

    public function test_a_stage_reached_out_of_turn_is_turned_away(): void
    {
        // Finance cannot review a run HR has not approved yet.
        $finance = $this->userWith('payroll-officer');
        $run = $this->runAt('processed');

        $this->actingAs($finance)->get("/payroll/{$run->id}/finance-review")->assertRedirect();
    }
}
