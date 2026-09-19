<?php

namespace Tests\Feature;

use App\Models\Appraisal;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * An appraisal goes to the person the employee reports to.
 *
 * `employees.manager_id` already records the reporting line, but the appraiser
 * list was built purely from system roles. A managing director who had never
 * been given a role was therefore unpickable as the appraiser of the HR manager
 * who reports to him — the org chart overruled by a permissions table.
 */
class AppraisalLineManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // appraiserChoices() asks Spatie for these by name, and Spatie throws
        // rather than returning nothing when one is absent.
        foreach (['account-manager', 'manager', 'hr-admin', 'super-admin', 'md', 'client'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function user(string $name, ?string $role = null): User
    {
        $user = User::factory()->create(['name' => $name]);

        if ($role) {
            Role::findOrCreate($role, 'web');
            $user->assignRole($role);
        }

        return $user;
    }

    private function employee(User $user, ?Employee $manager = null): Employee
    {
        return Employee::create([
            'user_id' => $user->id,
            'emp_number' => 'EMP-'.$user->id,
            'first_name' => explode(' ', $user->name)[0],
            'last_name' => explode(' ', $user->name.' X')[1],
            'hire_date' => '2024-01-15',
            'status' => 'active',
            'manager_id' => $manager?->id,
        ]);
    }

    /**
     * The shape that prompted this: Ian is HR and reports to Akol, the MD.
     * Neither account carries a system role.
     */
    private function hrReportingToMd(): array
    {
        $supervisor = $this->user('Supervisor', 'hr-admin');

        $md = $this->user('Akol Deograceous');
        $mdEmployee = $this->employee($md);

        $hr = $this->user('Ian Kirabo');
        $hrEmployee = $this->employee($hr, $mdEmployee);

        $appraisal = Appraisal::create([
            'title' => 'Annual review 2026',
            'employee_id' => $hrEmployee->id,
            'year' => 2026,
            'period' => 'Annual',
            'initiated_by' => $supervisor->id,
            'status' => 'draft',
        ]);

        return [$appraisal, $supervisor, $md, $hrEmployee];
    }

    public function test_the_line_manager_can_be_chosen_even_with_no_system_role(): void
    {
        [$appraisal, $supervisor, $md] = $this->hrReportingToMd();

        $this->assertEmpty($md->getRoleNames(), 'The point of the test is that he holds no role.');

        $this->actingAs($supervisor)
            ->get(route('appraisals.edit', $appraisal))
            ->assertOk()
            ->assertSee('Akol Deograceous');
    }

    public function test_the_line_manager_is_preselected(): void
    {
        [$appraisal, $supervisor, $md] = $this->hrReportingToMd();

        $this->actingAs($supervisor)
            ->get(route('appraisals.edit', $appraisal))
            ->assertOk()
            ->assertSee('value="'.$md->id.'" selected', false)
            ->assertSee('line manager');
    }

    /** Offered, not forced — a supervisor can still send it elsewhere. */
    public function test_somebody_else_can_still_be_chosen(): void
    {
        [$appraisal, $supervisor] = $this->hrReportingToMd();
        $other = $this->user('Account Manager', 'account-manager');

        $this->actingAs($supervisor)
            ->get(route('appraisals.edit', $appraisal))
            ->assertOk()
            ->assertSee('Account Manager');
    }

    /** An existing choice wins over the suggestion; it was made deliberately. */
    public function test_an_appraiser_already_named_is_not_overridden(): void
    {
        [$appraisal, $supervisor] = $this->hrReportingToMd();
        $other = $this->user('Account Manager', 'account-manager');

        $appraisal->update(['appraiser_id' => $other->id]);

        $this->actingAs($supervisor)
            ->get(route('appraisals.edit', $appraisal))
            ->assertOk()
            ->assertSee('value="'.$other->id.'" selected', false);
    }

    /** Nobody is invented when the employee has no manager recorded. */
    public function test_an_employee_with_no_manager_gets_no_suggestion(): void
    {
        $supervisor = $this->user('Supervisor', 'hr-admin');
        $staff = $this->employee($this->user('Solo Worker'));

        $appraisal = Appraisal::create([
            'title' => 'Annual review 2026',
            'employee_id' => $staff->id,
            'year' => 2026,
            'period' => 'Annual',
            'initiated_by' => $supervisor->id,
            'status' => 'draft',
        ]);

        $this->actingAs($supervisor)
            ->get(route('appraisals.edit', $appraisal))
            ->assertOk()
            ->assertDontSee('line manager');
    }

    /** A manager who does hold a role must not appear twice in the list. */
    public function test_a_manager_with_a_role_is_listed_once(): void
    {
        $supervisor = $this->user('Supervisor', 'hr-admin');

        $md = $this->user('Akol Deograceous', 'md');
        $mdEmployee = $this->employee($md);
        $staff = $this->employee($this->user('Ian Kirabo'), $mdEmployee);

        $appraisal = Appraisal::create([
            'title' => 'Annual review 2026',
            'employee_id' => $staff->id,
            'year' => 2026,
            'period' => 'Annual',
            'initiated_by' => $supervisor->id,
            'status' => 'draft',
        ]);

        $html = $this->actingAs($supervisor)
            ->get(route('appraisals.edit', $appraisal))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'value="'.$md->id.'"'));
    }
}
