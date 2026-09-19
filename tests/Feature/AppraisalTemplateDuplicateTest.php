<?php

namespace Tests\Feature;

use App\Models\Appraisal;
use App\Models\AppraisalTemplate;
use App\Models\Employee;
use App\Models\AppraisalTemplateKpi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Starting next cycle from last cycle's template.
 *
 * A scorecard is nineteen KRAs with measures, targets and weights balanced to
 * exactly 100%. Next cycle wants the same shape with a few targets moved, and
 * the only two ways to get there were retyping the lot or editing the original
 * in place — which quietly rewrites the template that finished appraisals were
 * measured against.
 */
class AppraisalTemplateDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('super-admin', 'web');

        $user = User::factory()->create();
        $user->assignRole('super-admin');

        return $user;
    }

    private function template(string $name = 'Operations Officer'): AppraisalTemplate
    {
        return AppraisalTemplate::create([
            'name' => $name,
            'description' => 'Field operations scorecard',
            'job_title' => 'Operations Officer',
            'financial_weight' => 30,
            'customer_weight' => 35,
            'internal_process_weight' => 25,
            'learning_growth_weight' => 10,
            'is_active' => true,
        ]);
    }

    private function kpi(AppraisalTemplate $t, string $perspective, string $name, float $weight, int $order): AppraisalTemplateKpi
    {
        return AppraisalTemplateKpi::create([
            'appraisal_template_id' => $t->id,
            'perspective' => $perspective,
            'kra_name' => $name,
            'performance_measure' => 'Measured monthly',
            'target' => '3%',
            'weightage' => $weight,
            'evidence_note' => 'Monthly report',
            'sort_order' => $order,
        ]);
    }

    private function employee(): Employee
    {
        $user = User::factory()->create();

        return Employee::create([
            'user_id' => $user->id,
            'emp_number' => 'EMP-'.$user->id,
            'first_name' => 'Aisha',
            'last_name' => 'Nakato',
            'hire_date' => '2024-01-15',
            'status' => 'active',
        ]);
    }

    private function copyOf(AppraisalTemplate $t): AppraisalTemplate
    {
        return AppraisalTemplate::where('id', '!=', $t->id)->latest('id')->firstOrFail();
    }

    // ── The copy itself ──────────────────────────────────────────────────

    public function test_the_copy_carries_every_kpi(): void
    {
        $template = $this->template();
        $this->kpi($template, 'financial', 'Reduce operational cost', 30, 1);
        $this->kpi($template, 'customer', 'Client retention', 35, 2);
        $this->kpi($template, 'internal_process', 'Incident closure', 25, 3);
        $this->kpi($template, 'learning_growth', 'Staff training days', 10, 4);

        $this->actingAs($this->admin())
            ->post(route('admin.appraisal-templates.duplicate', $template))
            ->assertRedirect();

        $copy = $this->copyOf($template);

        $this->assertSame(4, $copy->kpis()->count());
        $this->assertEqualsWithDelta(100.0, $copy->kpiWeight(), 0.01,
            'A copy that loses weight is not the same scorecard.');
    }

    /** Every field, not merely the name: a half-copied KRA reads as a real one. */
    public function test_each_kpi_keeps_its_detail(): void
    {
        $template = $this->template();
        $this->kpi($template, 'customer', 'Client retention', 35, 7);

        $this->actingAs($this->admin())
            ->post(route('admin.appraisal-templates.duplicate', $template));

        $original = $template->kpis()->first();
        $copied = $this->copyOf($template)->kpis()->first();

        foreach (['perspective', 'kra_name', 'performance_measure', 'target', 'weightage', 'evidence_note', 'sort_order'] as $field) {
            $this->assertSame(
                (string) $original->$field,
                (string) $copied->$field,
                "The copy lost {$field}."
            );
        }
    }

    public function test_the_perspective_split_is_carried_across(): void
    {
        $template = $this->template();

        $this->actingAs($this->admin())
            ->post(route('admin.appraisal-templates.duplicate', $template));

        $copy = $this->copyOf($template);

        $this->assertSame($template->perspectiveWeights(), $copy->perspectiveWeights());
        $this->assertTrue($copy->weightIsComplete());
        $this->assertSame('Operations Officer', $copy->job_title);
    }

    /**
     * The order somebody arranged, not id order. sort_order is assigned on
     * create everywhere else, so a copy that reassigns it reshuffles the card.
     */
    public function test_the_kpi_order_survives(): void
    {
        $template = $this->template();
        $this->kpi($template, 'financial', 'Third', 10, 30);
        $this->kpi($template, 'financial', 'First', 10, 10);
        $this->kpi($template, 'financial', 'Second', 10, 20);

        $this->actingAs($this->admin())
            ->post(route('admin.appraisal-templates.duplicate', $template));

        $this->assertSame(
            ['First', 'Second', 'Third'],
            $this->copyOf($template)->kpis()->pluck('kra_name')->all()
        );
    }

    // ── The original is left alone ───────────────────────────────────────

    public function test_the_original_is_untouched(): void
    {
        $template = $this->template();
        $this->kpi($template, 'financial', 'Reduce operational cost', 30, 1);

        $this->actingAs($this->admin())
            ->post(route('admin.appraisal-templates.duplicate', $template));

        $template->refresh();

        $this->assertSame('Operations Officer', $template->name);
        $this->assertTrue($template->is_active, 'Duplicating must not switch the original off.');
        $this->assertSame(1, $template->kpis()->count(), 'The KPIs were moved instead of copied.');
    }

    /** The whole reason not to edit last cycle's template in place. */
    public function test_a_finished_appraisal_still_points_at_the_original(): void
    {
        $template = $this->template();
        $this->kpi($template, 'financial', 'Reduce operational cost', 30, 1);

        $admin = $this->admin();

        $appraisal = Appraisal::create([
            'title' => 'Annual review 2026',
            'employee_id' => $this->employee()->id,
            'year' => 2026,
            'period' => 'Annual',
            'initiated_by' => $admin->id,
            'appraiser_id' => $admin->id,
            'appraisal_template_id' => $template->id,
            'status' => 'completed',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.appraisal-templates.duplicate', $template));

        $this->assertSame($template->id, $appraisal->refresh()->appraisal_template_id);
    }

    // ── How it arrives ───────────────────────────────────────────────────

    /**
     * Inactive on purpose. Two identical active templates in the picker is how a
     * supervisor applies the half-edited one.
     */
    public function test_the_copy_arrives_switched_off(): void
    {
        $template = $this->template();

        $this->actingAs($this->admin())
            ->post(route('admin.appraisal-templates.duplicate', $template));

        $this->assertFalse($this->copyOf($template)->is_active);
    }

    public function test_the_copy_is_named_apart_from_the_original(): void
    {
        $template = $this->template();

        $this->actingAs($this->admin())
            ->post(route('admin.appraisal-templates.duplicate', $template));

        $this->assertSame('Operations Officer (copy)', $this->copyOf($template)->name);
    }

    /** Copying twice must not produce two rows reading the same thing. */
    public function test_a_second_copy_gets_its_own_name(): void
    {
        $template = $this->template();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.appraisal-templates.duplicate', $template));
        $this->actingAs($admin)->post(route('admin.appraisal-templates.duplicate', $template));

        $this->assertSame(
            ['Operations Officer', 'Operations Officer (copy)', 'Operations Officer (copy 2)'],
            AppraisalTemplate::orderBy('id')->pluck('name')->all()
        );
    }

    /** Copying a copy, rather than "(copy) (copy)". */
    public function test_copying_a_copy_does_not_stack_suffixes(): void
    {
        $template = $this->template('Operations Officer (copy)');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.appraisal-templates.duplicate', $template));

        $this->assertSame('Operations Officer (copy 2)', $this->copyOf($template)->name);
    }

    /** name is varchar(255); an overflowing suffix would be a 500, not a copy. */
    public function test_a_very_long_name_still_copies(): void
    {
        $template = $this->template(str_repeat('A', 255));

        $this->actingAs($this->admin())
            ->post(route('admin.appraisal-templates.duplicate', $template))
            ->assertRedirect();

        $copy = $this->copyOf($template);

        $this->assertLessThanOrEqual(255, mb_strlen($copy->name));
        $this->assertStringEndsWith('(copy)', $copy->name);
    }

    public function test_the_copy_is_owned_by_whoever_made_it(): void
    {
        $template = $this->template();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.appraisal-templates.duplicate', $template));

        $this->assertSame($admin->id, $this->copyOf($template)->created_by);
    }

    // ── Who may do it ────────────────────────────────────────────────────

    public function test_an_ordinary_user_cannot_duplicate_a_template(): void
    {
        $template = $this->template();
        Role::findOrCreate('employee', 'web');

        $user = User::factory()->create();
        $user->assignRole('employee');

        $this->actingAs($user)
            ->post(route('admin.appraisal-templates.duplicate', $template))
            ->assertForbidden();

        $this->assertSame(1, AppraisalTemplate::count());
    }

    // ── The screen ───────────────────────────────────────────────────────

    public function test_the_list_offers_the_copy(): void
    {
        $template = $this->template();

        $this->actingAs($this->admin())
            ->get(route('admin.appraisal-templates.index'))
            ->assertOk()
            ->assertSee(route('admin.appraisal-templates.duplicate', $template))
            ->assertSee('Duplicate');
    }

    /** A copy nobody can apply, with nothing on screen saying why, reads as a bug. */
    public function test_the_copy_says_it_is_switched_off(): void
    {
        $template = $this->template();

        $this->actingAs($this->admin())
            ->post(route('admin.appraisal-templates.duplicate', $template));

        $this->actingAs($this->admin())
            ->get(route('admin.appraisal-templates.edit', $this->copyOf($template)))
            ->assertOk()
            ->assertSee('supervisors cannot apply it yet', false);
    }
}
