<?php

namespace Tests\Feature;

use App\Models\Appraisal;
use App\Models\AppraisalKpi;
use App\Models\AppraisalTemplate;
use App\Models\AppraisalTemplateKpi;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Two things that made a card impossible to finish.
 *
 * Somebody on approved leave vanished from the list of people an appraisal
 * could be started for — a two-day absence removed them for the whole quarter.
 * And a KRA already on a card kept its own weight when a template was imported
 * over it, so the card landed on 99% with nothing on screen saying which row
 * was responsible.
 */
class AppraisalPickerAndWeightsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super-admin', 'hr-admin', 'account-manager', 'manager', 'md', 'client', 'employee'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super-admin');
    }

    private function staff(string $number, string $first, string $status): Employee
    {
        return Employee::create([
            'user_id' => User::factory()->create(['name' => $first.' Person'])->id,
            'emp_number' => $number,
            'first_name' => $first,
            'last_name' => 'Person',
            'hire_date' => '2024-01-15',
            'status' => $status,
        ]);
    }

    // ── The picker ───────────────────────────────────────────────────────

    public function test_somebody_on_leave_can_still_be_appraised(): void
    {
        $this->staff('HQ001', 'Ian', 'on_leave');

        $this->actingAs($this->admin)
            ->get(route('appraisals.create'))
            ->assertOk()
            ->assertSee('Ian Person');
    }

    public function test_active_staff_are_listed_as_before(): void
    {
        $this->staff('HQ002', 'Akol', 'active');

        $this->actingAs($this->admin)
            ->get(route('appraisals.create'))
            ->assertOk()
            ->assertSee('Akol Person');
    }

    /** Somebody who has actually left is not appraised for the quarter. */
    public function test_a_terminated_employee_is_not_listed(): void
    {
        $this->staff('HQ003', 'Departed', 'terminated');

        $this->actingAs($this->admin)
            ->get(route('appraisals.create'))
            ->assertOk()
            ->assertDontSee('Departed Person');
    }

    public function test_a_suspended_employee_is_not_listed(): void
    {
        $this->staff('HQ004', 'Suspended', 'suspended');

        $this->actingAs($this->admin)
            ->get(route('appraisals.create'))
            ->assertOk()
            ->assertDontSee('Suspended Person');
    }

    // ── The weight that does not match ───────────────────────────────────

    public function test_a_skipped_kra_with_a_different_weight_is_named(): void
    {
        $employee = $this->staff('HQ001', 'Ian', 'active');

        $appraisal = Appraisal::create([
            'title' => 'Quarterly Appraisal 2026',
            'employee_id' => $employee->id,
            'year' => 2026,
            'initiated_by' => $this->admin->id,
            'status' => 'draft',
        ]);

        // The card carries 5%; the template says 6%.
        AppraisalKpi::create([
            'appraisal_id' => $appraisal->id,
            'perspective' => 'financial',
            'kra_name' => 'Acquire New  2 Business',
            'weightage' => 5,
            'sort_order' => 1,
        ]);

        $template = AppraisalTemplate::create([
            'name' => 'Human Resource Management',
            'financial_weight' => 100, 'customer_weight' => 0,
            'internal_process_weight' => 0, 'learning_growth_weight' => 0,
            'is_active' => true,
        ]);

        foreach ([['Acquire New  2 Business', 6], ['Customer Retention', 94]] as $i => [$name, $weight]) {
            AppraisalTemplateKpi::create([
                'appraisal_template_id' => $template->id,
                'perspective' => 'financial',
                'kra_name' => $name,
                'weightage' => $weight,
                'sort_order' => $i + 1,
            ]);
        }

        $this->actingAs($this->admin)
            ->post(route('appraisals.import-kpis', $appraisal), [
                'appraisal_template_id' => $template->id,
            ])
            ->assertRedirect();

        // 5 + 94 = 99, not 100, and the message must say which row is why.
        $this->assertEqualsWithDelta(99, $appraisal->refresh()->totalWeight(), 0.001);

        $message = session('success');

        $this->assertStringContainsString('Acquire New  2 Business', $message);
        $this->assertStringContainsString('card 5%', $message);
        $this->assertStringContainsString('template 6%', $message);
    }

    public function test_nothing_is_said_when_the_weights_do_total_100(): void
    {
        $employee = $this->staff('HQ001', 'Ian', 'active');

        $appraisal = Appraisal::create([
            'title' => 'Quarterly Appraisal 2026',
            'employee_id' => $employee->id,
            'year' => 2026,
            'initiated_by' => $this->admin->id,
            'status' => 'draft',
        ]);

        $template = AppraisalTemplate::create([
            'name' => 'Clean Template',
            'financial_weight' => 100, 'customer_weight' => 0,
            'internal_process_weight' => 0, 'learning_growth_weight' => 0,
            'is_active' => true,
        ]);

        AppraisalTemplateKpi::create([
            'appraisal_template_id' => $template->id,
            'perspective' => 'financial',
            'kra_name' => 'Customer Retention',
            'weightage' => 100,
            'sort_order' => 1,
        ]);

        $this->actingAs($this->admin)
            ->post(route('appraisals.import-kpis', $appraisal), [
                'appraisal_template_id' => $template->id,
            ]);

        $this->assertEqualsWithDelta(100, $appraisal->refresh()->totalWeight(), 0.001);
        $this->assertStringNotContainsString('do not total', (string) session('success'));
    }
}
