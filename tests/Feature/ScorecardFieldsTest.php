<?php

namespace Tests\Feature;

use App\Models\Appraisal;
use App\Models\AppraisalKpi;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Three columns on the balanced scorecard that could not be filled in.
 *
 * Target was display-only, so a KPI copied from a template without one could
 * never be given a target — and "% Target Achieved" is derived from it, so that
 * column stayed empty as well. Self-rating could only be set during the
 * employee's own step. And the weighted index reads "—" until a rating exists,
 * because it is derived from one.
 */
class ScorecardFieldsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $appraiser;
    private Appraisal $appraisal;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super-admin', 'hr-admin', 'account-manager', 'manager', 'md', 'client', 'employee'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super-admin');

        $this->appraiser = User::factory()->create();
        $this->appraiser->assignRole('manager');

        $employee = Employee::create([
            'user_id' => User::factory()->create()->id,
            'emp_number' => 'HQ001',
            'first_name' => 'Ian',
            'last_name' => 'Kirabo',
            'hire_date' => '2024-01-15',
            'status' => 'active',
        ]);

        $this->appraisal = Appraisal::create([
            'title' => 'Quarterly Appraisal 2026',
            'employee_id' => $employee->id,
            'year' => 2026,
            'initiated_by' => $this->admin->id,
            'appraiser_id' => $this->appraiser->id,
            'status' => 'with_appraiser',
        ]);
    }

    private function kpi(float $weight = 5): AppraisalKpi
    {
        return AppraisalKpi::create([
            'appraisal_id' => $this->appraisal->id,
            'perspective' => 'financial',
            'kra_name' => 'Customer Retention',
            'performance_measure' => 'Retain all Existing Clients',
            'weightage' => $weight,
            'sort_order' => 1,
        ]);
    }

    private function score(User $as, AppraisalKpi $kpi, array $fields)
    {
        return $this->actingAs($as)->post(route('appraisals.score', $this->appraisal), [
            'kpi' => [$kpi->id => $fields],
        ]);
    }

    // ── The weighted index ───────────────────────────────────────────────

    /** A 3 against a 5% weight is 0.15. */
    public function test_three_on_a_five_percent_weight_gives_nought_point_one_five(): void
    {
        $kpi = $this->kpi(5);

        $this->score($this->admin, $kpi, ['rating' => 3])->assertRedirect();

        $this->assertEqualsWithDelta(0.15, (float) $kpi->refresh()->weighted_index, 0.0001);
    }

    public function test_the_index_is_shown_to_two_decimals(): void
    {
        $kpi = $this->kpi(5);
        $this->score($this->admin, $kpi, ['rating' => 3]);

        $this->actingAs($this->admin)
            ->get(route('appraisals.show', $this->appraisal))
            ->assertOk()
            ->assertSee('0.15');
    }

    public function test_a_four_percent_weight_scales_the_same_way(): void
    {
        $kpi = $this->kpi(4);
        $this->score($this->admin, $kpi, ['rating' => 5]);

        $this->assertEqualsWithDelta(0.20, (float) $kpi->refresh()->weighted_index, 0.0001);
    }

    // ── The notification email ───────────────────────────────────────────

    /**
     * Mail views only fail at render time, so this renders one.
     *
     * The template variable could not be called $message: Laravel injects its
     * own Illuminate\Mail\Message into every mail view under that name, which
     * shadowed the property and made the view try to print an object. The class
     * was also invisible on the server until the optimised autoloader was
     * regenerated - both faults appeared only when a real email was attempted.
     */
    public function test_the_appraisal_email_renders(): void
    {
        $mail = new \App\Mail\AppraisalNotificationMail(
            $this->appraisal,
            'Your appraisal is open',
            'Record what you achieved against each target.',
            'Akol Deograceous'
        );

        $html = $mail->render();

        $this->assertStringContainsString('Your appraisal is open', $html);
        $this->assertStringContainsString('Record what you achieved', $html);
        $this->assertStringContainsString('Akol Deograceous', $html);
        $this->assertStringContainsString('appraisals/'.$this->appraisal->id, $html);
    }

    public function test_the_subject_is_the_heading(): void
    {
        $mail = new \App\Mail\AppraisalNotificationMail(
            $this->appraisal, 'Appraisal ready for scoring', 'Body text.', 'Ian'
        );

        $this->assertSame('Appraisal ready for scoring', $mail->envelope()->subject);
    }

    // ── Target ───────────────────────────────────────────────────────────

    public function test_a_target_can_be_entered_while_scoring(): void
    {
        $kpi = $this->kpi();

        $this->assertNull($kpi->target);

        $this->score($this->admin, $kpi, ['target' => '30', 'rating' => 4])->assertRedirect();

        $this->assertSame('30', $kpi->refresh()->target);
    }

    /** "% Target Achieved" is derived from the target, so it only works once one exists. */
    public function test_the_percentage_achieved_follows_from_the_target(): void
    {
        $kpi = $this->kpi();

        $this->score($this->admin, $kpi, ['target' => '40', 'actual_achieved' => '30', 'rating' => 3]);

        $this->assertEqualsWithDelta(75.0, (float) $kpi->refresh()->target_percent, 0.01);
    }

    public function test_the_scorecard_offers_a_target_box(): void
    {
        $this->kpi();

        $this->actingAs($this->admin)
            ->get(route('appraisals.show', $this->appraisal))
            ->assertOk()
            ->assertSee('[target]', false);
    }

    // ── Self-rating ──────────────────────────────────────────────────────

    public function test_an_administrator_can_record_a_self_rating(): void
    {
        $kpi = $this->kpi();

        $this->score($this->admin, $kpi, ['rating' => 4, 'self_rating' => 5])->assertRedirect();

        $this->assertSame(5, $kpi->refresh()->self_rating);
        $this->assertSame(4, $kpi->rating, "The appraiser's own rating is separate.");
    }

    /**
     * Writing somebody else's self-rating is not scoring, it is inventing, so
     * an ordinary appraiser cannot.
     */
    public function test_an_ordinary_appraiser_cannot_write_a_self_rating(): void
    {
        $kpi = $this->kpi();

        $this->score($this->appraiser, $kpi, ['rating' => 4, 'self_rating' => 5])->assertRedirect();

        $kpi->refresh();

        $this->assertSame(4, $kpi->rating, 'Their own rating should still save.');
        $this->assertNull($kpi->self_rating, "The employee's self-rating was written by somebody else.");
    }

    public function test_the_two_ratings_are_kept_apart(): void
    {
        $kpi = $this->kpi(5);

        $this->score($this->admin, $kpi, ['rating' => 2, 'self_rating' => 5]);

        $kpi->refresh();

        $this->assertSame(2, $kpi->rating);
        $this->assertSame(5, $kpi->self_rating);
        // The index follows the appraiser's rating, not the self-rating.
        $this->assertEqualsWithDelta(0.10, (float) $kpi->weighted_index, 0.0001);
    }
}
