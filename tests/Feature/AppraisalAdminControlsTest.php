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
 * An administrator working a card that is not at their step.
 *
 * Every control on the scorecard was gated on being one named person at one
 * named stage. HR opening a card mid-flight could change nothing at all — not
 * a rating, not a comment — and a card built before its template was finished
 * could only be filled one KRA at a time.
 */
class AppraisalAdminControlsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $appraiser;
    private Employee $employee;
    private Appraisal $appraisal;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super-admin', 'hr-admin', 'account-manager', 'manager', 'md', 'client', 'employee'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->admin = User::factory()->create(['name' => 'System Admin']);
        $this->admin->assignRole('super-admin');

        $this->appraiser = User::factory()->create(['name' => 'Akol Deograceous']);

        $staffUser = User::factory()->create(['name' => 'Ian Kirabo']);
        $this->employee = Employee::create([
            'user_id' => $staffUser->id,
            'emp_number' => 'HQ001',
            'first_name' => 'Ian',
            'last_name' => 'Kirabo',
            'hire_date' => '2024-01-15',
            'status' => 'active',
        ]);

        $this->appraisal = Appraisal::create([
            'title' => 'Quarterly Appraisal 2026',
            'employee_id' => $this->employee->id,
            'year' => 2026,
            'period' => 'Q1 2026',
            'initiated_by' => $this->admin->id,
            'appraiser_id' => $this->appraiser->id,
            'status' => 'with_appraiser',
        ]);
    }

    private function kpi(string $name, float $weight, string $perspective = 'financial'): AppraisalKpi
    {
        return AppraisalKpi::create([
            'appraisal_id' => $this->appraisal->id,
            'perspective' => $perspective,
            'kra_name' => $name,
            'performance_measure' => 'Measured quarterly',
            'weightage' => $weight,
            'sort_order' => AppraisalKpi::where('appraisal_id', $this->appraisal->id)->count() + 1,
        ]);
    }

    private function template(array $rows): AppraisalTemplate
    {
        $template = AppraisalTemplate::create([
            'name' => 'Human Resource Management',
            'financial_weight' => 25, 'customer_weight' => 35,
            'internal_process_weight' => 28, 'learning_growth_weight' => 12,
            'is_active' => true,
        ]);

        foreach ($rows as $i => [$name, $weight, $perspective]) {
            AppraisalTemplateKpi::create([
                'appraisal_template_id' => $template->id,
                'perspective' => $perspective,
                'kra_name' => $name,
                'performance_measure' => 'From the template',
                'weightage' => $weight,
                'sort_order' => $i + 1,
            ]);
        }

        return $template;
    }

    // ── Scoring ──────────────────────────────────────────────────────────

    public function test_an_administrator_can_score_a_card_they_are_not_the_appraiser_on(): void
    {
        $kpi = $this->kpi('Customer Retention', 5);

        $this->actingAs($this->admin)
            ->post(route('appraisals.score', $this->appraisal), [
                'kpi' => [$kpi->id => ['rating' => 4, 'actual_achieved' => '100%']],
            ])
            ->assertRedirect();

        $this->assertSame(4, $kpi->refresh()->rating);
    }

    /**
     * rating x weight%: a 4 on a 5% weight is 0.20. Derived on save rather
     * than posted, so it can never disagree with the rating beside it.
     */
    public function test_the_weighted_index_is_rating_times_weight(): void
    {
        $kpi = $this->kpi('Customer Retention', 5);

        $this->actingAs($this->admin)
            ->post(route('appraisals.score', $this->appraisal), [
                'kpi' => [$kpi->id => ['rating' => 4]],
            ]);

        $this->assertEqualsWithDelta(0.20, (float) $kpi->refresh()->weighted_index, 0.0001);
    }

    public function test_the_index_adds_up_across_the_card(): void
    {
        $a = $this->kpi('Customer Retention', 5);
        $b = $this->kpi('Acquire New Business', 5);
        $c = $this->kpi('Account Development', 4);

        $this->actingAs($this->admin)
            ->post(route('appraisals.score', $this->appraisal), [
                'kpi' => [
                    $a->id => ['rating' => 4],
                    $b->id => ['rating' => 5],
                    $c->id => ['rating' => 3],
                ],
            ]);

        // 4x0.05 + 5x0.05 + 3x0.04 = 0.20 + 0.25 + 0.12
        $this->assertEqualsWithDelta(
            0.57,
            (float) AppraisalKpi::where('appraisal_id', $this->appraisal->id)->sum('weighted_index'),
            0.0001
        );
    }

    public function test_clearing_a_rating_clears_its_index(): void
    {
        $kpi = $this->kpi('Customer Retention', 5);

        $this->actingAs($this->admin)->post(route('appraisals.score', $this->appraisal), [
            'kpi' => [$kpi->id => ['rating' => 4]],
        ]);
        $this->assertNotNull($kpi->refresh()->weighted_index);

        $this->actingAs($this->admin)->post(route('appraisals.score', $this->appraisal), [
            'kpi' => [$kpi->id => ['rating' => null]],
        ]);

        $this->assertNull($kpi->refresh()->weighted_index);
    }

    public function test_somebody_with_no_business_here_still_cannot_score(): void
    {
        $kpi = $this->kpi('Customer Retention', 5);

        $stranger = User::factory()->create();
        $stranger->assignRole('employee');

        $this->actingAs($stranger)
            ->post(route('appraisals.score', $this->appraisal), [
                'kpi' => [$kpi->id => ['rating' => 5]],
            ])
            ->assertForbidden();

        $this->assertNull($kpi->refresh()->rating);
    }

    // ── Importing KPIs ───────────────────────────────────────────────────

    public function test_an_administrator_can_import_a_templates_kpis(): void
    {
        $this->kpi('Customer Retention', 5);

        $template = $this->template([
            ['Customer Retention', 5, 'financial'],
            ['Leave liability Management', 5, 'financial'],
            ['Resolution of Customer Complaints in 24 Hours', 10, 'customer'],
        ]);

        $this->actingAs($this->admin)
            ->post(route('appraisals.import-kpis', $this->appraisal), [
                'appraisal_template_id' => $template->id,
            ])
            ->assertRedirect();

        $names = AppraisalKpi::where('appraisal_id', $this->appraisal->id)->pluck('kra_name');

        $this->assertCount(3, $names, 'The KRA already on the card should not have been duplicated.');
        $this->assertContains('Leave liability Management', $names->all());
        $this->assertContains('Resolution of Customer Complaints in 24 Hours', $names->all());
    }

    public function test_importing_keeps_the_perspective_and_weight(): void
    {
        $template = $this->template([
            ['Handling Staff Grievances in 48Hrs', 7.5, 'customer'],
        ]);

        $this->actingAs($this->admin)
            ->post(route('appraisals.import-kpis', $this->appraisal), [
                'appraisal_template_id' => $template->id,
            ]);

        $kpi = AppraisalKpi::where('kra_name', 'Handling Staff Grievances in 48Hrs')->firstOrFail();

        $this->assertSame('customer', $kpi->perspective);
        $this->assertEqualsWithDelta(7.5, (float) $kpi->weightage, 0.001);
    }

    public function test_importing_the_same_template_twice_adds_nothing(): void
    {
        $template = $this->template([['Customer Retention', 5, 'financial']]);

        $this->actingAs($this->admin)->post(route('appraisals.import-kpis', $this->appraisal), [
            'appraisal_template_id' => $template->id,
        ]);
        $this->actingAs($this->admin)->post(route('appraisals.import-kpis', $this->appraisal), [
            'appraisal_template_id' => $template->id,
        ]);

        $this->assertSame(1, AppraisalKpi::where('appraisal_id', $this->appraisal->id)->count());
    }

    public function test_an_ordinary_user_cannot_import(): void
    {
        $template = $this->template([['Customer Retention', 5, 'financial']]);

        $stranger = User::factory()->create();
        $stranger->assignRole('employee');

        $this->actingAs($stranger)
            ->post(route('appraisals.import-kpis', $this->appraisal), [
                'appraisal_template_id' => $template->id,
            ])
            ->assertForbidden();

        $this->assertSame(0, AppraisalKpi::where('appraisal_id', $this->appraisal->id)->count());
    }

    // ── Comments ─────────────────────────────────────────────────────────

    public function test_an_administrator_can_write_both_comments_at_any_stage(): void
    {
        $this->actingAs($this->admin)
            ->post(route('appraisals.comments', $this->appraisal), [
                'manager_comment'  => 'Strong quarter on retention.',
                'employee_comment' => 'Dictated over the phone.',
            ])
            ->assertRedirect();

        $this->appraisal->refresh();

        $this->assertSame('Strong quarter on retention.', $this->appraisal->manager_comment);
        $this->assertSame('Dictated over the phone.', $this->appraisal->employee_comment);
    }

    /** Editing somebody else's comment must never be invisible. */
    public function test_editing_comments_is_written_into_the_history(): void
    {
        $this->actingAs($this->admin)
            ->post(route('appraisals.comments', $this->appraisal), [
                'manager_comment' => 'Corrected a typo.',
            ]);

        $this->assertDatabaseHas('appraisal_history', [
            'appraisal_id' => $this->appraisal->id,
            'action' => 'comments_edited',
        ]);
    }

    public function test_an_ordinary_user_cannot_edit_the_comments(): void
    {
        $stranger = User::factory()->create();
        $stranger->assignRole('employee');

        $this->actingAs($stranger)
            ->post(route('appraisals.comments', $this->appraisal), ['manager_comment' => 'Nope.'])
            ->assertForbidden();

        $this->assertNull($this->appraisal->refresh()->manager_comment);
    }

    // ── Deleting ─────────────────────────────────────────────────────────

    public function test_an_administrator_can_delete_an_unscored_card(): void
    {
        $this->appraisal->update(['status' => 'draft']);
        $this->kpi('Customer Retention', 5);

        $this->actingAs($this->admin)
            ->delete(route('appraisals.destroy', $this->appraisal))
            ->assertRedirect(route('appraisals.index'));

        $this->assertDatabaseMissing('appraisals', ['id' => $this->appraisal->id]);
    }

    /**
     * Once ratings exist the card records a conversation that happened.
     * Deleting it destroys that rather than tidying it.
     */
    public function test_a_scored_card_is_not_deleted(): void
    {
        $kpi = $this->kpi('Customer Retention', 5);
        $kpi->update(['rating' => 4]);

        $this->actingAs($this->admin)
            ->delete(route('appraisals.destroy', $this->appraisal))
            ->assertRedirect();

        $this->assertDatabaseHas('appraisals', ['id' => $this->appraisal->id]);
    }

    public function test_an_ordinary_user_cannot_delete(): void
    {
        $stranger = User::factory()->create();
        $stranger->assignRole('employee');

        $this->actingAs($stranger)
            ->delete(route('appraisals.destroy', $this->appraisal))
            ->assertForbidden();

        $this->assertDatabaseHas('appraisals', ['id' => $this->appraisal->id]);
    }

    // ── The screen ───────────────────────────────────────────────────────

    public function test_the_card_offers_an_administrator_the_controls(): void
    {
        $this->template([['Customer Retention', 5, 'financial']]);

        $this->actingAs($this->admin)
            ->get(route('appraisals.show', $this->appraisal))
            ->assertOk()
            ->assertSee('Administrator controls')
            ->assertSee('Import KPIs from a template')
            ->assertSee('Delete appraisal');
    }

    public function test_the_employee_whose_card_it_is_does_not_get_them(): void
    {
        $staff = $this->employee->user;
        $staff->assignRole('employee');

        $this->actingAs($staff)
            ->get(route('appraisals.show', $this->appraisal))
            ->assertOk()
            ->assertDontSee('Administrator controls')
            ->assertDontSee('Delete appraisal');
    }
}
