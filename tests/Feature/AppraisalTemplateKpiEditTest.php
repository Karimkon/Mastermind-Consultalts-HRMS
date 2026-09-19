<?php

namespace Tests\Feature;

use App\Models\AppraisalTemplate;
use App\Models\AppraisalTemplateKpi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Correcting a KPI on a template without rebuilding it.
 *
 * There was only add and delete. Fixing a typo in a target meant deleting the row
 * and retyping it — and because `sort_order` is assigned on create, the corrected
 * KPI reappeared at the bottom of its perspective. On a card of nineteen KRAs,
 * taken from a real balanced scorecard, that reshuffled the card every time
 * somebody fixed a number.
 */
class AppraisalTemplateKpiEditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('super-admin', 'web');

        $user = User::factory()->create();
        $user->assignRole('super-admin');

        return $user;
    }

    private function template(): AppraisalTemplate
    {
        return AppraisalTemplate::create([
            'name' => 'HR & Operations Manager',
            'job_title' => 'HR & Operations Manager',
            'financial_weight' => 30,
            'customer_weight' => 35,
            'internal_process_weight' => 25,
            'learning_growth_weight' => 10,
            'is_active' => true,
        ]);
    }

    private function kpi(AppraisalTemplate $template, float $weight, int $order = 1, string $perspective = 'financial'): AppraisalTemplateKpi
    {
        return AppraisalTemplateKpi::create([
            'appraisal_template_id' => $template->id,
            'perspective' => $perspective,
            'kra_name' => 'Reduce operational cost',
            'performance_measure' => '3% cost reduction',
            'target' => '3%',
            'weightage' => $weight,
            'sort_order' => $order,
        ]);
    }

    private function edit(User $as, AppraisalTemplate $template, AppraisalTemplateKpi $kpi, array $changes)
    {
        return $this->actingAs($as)->put(
            route('admin.appraisal-templates.kpis.update', [$template, $kpi]),
            array_merge([
                'perspective' => $kpi->perspective,
                'kra_name' => $kpi->kra_name,
                'performance_measure' => $kpi->performance_measure,
                'target' => $kpi->target,
                'weightage' => $kpi->weightage,
            ], $changes),
        );
    }

    public function test_a_kpi_can_be_corrected_in_place(): void
    {
        $template = $this->template();
        $kpi = $this->kpi($template, 5);

        $this->edit($this->admin(), $template, $kpi, [
            'kra_name' => 'Reduce operational costs',
            'target' => '5%',
            'weightage' => 30,
        ])->assertRedirect();

        $kpi->refresh();

        $this->assertSame('Reduce operational costs', $kpi->kra_name);
        $this->assertSame('5%', $kpi->target);
        $this->assertEquals(30, $kpi->weightage);
    }

    /** The whole point: a correction must not move the row. */
    public function test_editing_does_not_reorder_the_card(): void
    {
        $template = $this->template();
        $first = $this->kpi($template, 10, order: 1);
        $this->kpi($template, 10, order: 2);
        $this->kpi($template, 10, order: 3);

        $this->edit($this->admin(), $template, $first, ['target' => '9%']);

        $this->assertSame(1, $first->refresh()->sort_order, 'A corrected KPI should stay where it was.');
    }

    /**
     * The trap in reusing the create rule: measuring the ceiling against a total
     * that still includes this KPI's own weight would refuse an increase on a
     * template that has room for it.
     */
    public function test_a_kpi_can_be_raised_when_the_template_has_room(): void
    {
        $template = $this->template();
        $kpi = $this->kpi($template, 5);

        // 5% used of 100%. Raising this one to 40% leaves 60% free.
        $this->edit($this->admin(), $template, $kpi, ['weightage' => 40])->assertRedirect();

        $this->assertEquals(40, $kpi->refresh()->weightage);
    }

    public function test_a_kpi_cannot_be_raised_past_the_hundred_percent_ceiling(): void
    {
        $template = $this->template();
        $kpi = $this->kpi($template, 5, order: 1);
        $this->kpi($template, 70, order: 2, perspective: 'customer');

        // 75% spent. Taking this row to 40% would mean 110%.
        $this->edit($this->admin(), $template, $kpi, ['weightage' => 40])
            ->assertSessionHas('error');

        $this->assertEquals(5, $kpi->refresh()->weightage, 'The KPI should be unchanged.');
    }

    /** A KRA filed under the wrong perspective can be moved rather than rebuilt. */
    public function test_a_kpi_can_be_moved_to_another_perspective(): void
    {
        $template = $this->template();
        $kpi = $this->kpi($template, 5, perspective: 'financial');

        $this->edit($this->admin(), $template, $kpi, ['perspective' => 'learning_growth'])
            ->assertRedirect();

        $this->assertSame('learning_growth', $kpi->refresh()->perspective);
    }

    public function test_a_kpi_belonging_to_another_template_is_not_reachable(): void
    {
        $mine = $this->template();
        $theirs = AppraisalTemplate::create([
            'name' => 'Sales Executive',
            'financial_weight' => 42, 'customer_weight' => 20,
            'internal_process_weight' => 15, 'learning_growth_weight' => 15,
            'is_active' => true,
        ]);

        $kpi = $this->kpi($theirs, 5);

        $this->edit($this->admin(), $mine, $kpi, ['kra_name' => 'Hijacked'])
            ->assertNotFound();

        $this->assertSame('Reduce operational cost', $kpi->refresh()->kra_name);
    }

    public function test_an_ordinary_employee_cannot_edit_a_template(): void
    {
        Role::findOrCreate('employee', 'web');
        $user = User::factory()->create();
        $user->assignRole('employee');

        $template = $this->template();
        $kpi = $this->kpi($template, 5);

        $this->edit($user, $template, $kpi, ['kra_name' => 'Changed'])->assertForbidden();

        $this->assertSame('Reduce operational cost', $kpi->refresh()->kra_name);
    }
}
