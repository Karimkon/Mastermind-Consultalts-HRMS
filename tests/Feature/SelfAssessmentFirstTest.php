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
 * The employee speaks first.
 *
 * The card used to reach the person being appraised only at the end, to read a
 * finished score and sign it. It now goes to them before anybody rates them:
 *
 *     draft            HR or the manager sets the KRAs and weights
 *     self_assessment  the employee reports what they achieved, and self-rates
 *     with_appraiser   the appraiser sets the official rating
 *     with_manager     the line manager confirms
 *     with_employee    the employee reads the final scores and signs
 *     completed
 *
 * The employee touches the card twice, at opposite ends, and those two touches do
 * different things — which is the part most likely to be broken by a later
 * change, so it is what these tests pin down.
 */
class SelfAssessmentFirstTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'employee', string $name = 'Someone'): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create(['name' => $name]);
        $user->assignRole($role);

        return $user;
    }

    /** @return array{Appraisal, User, User, User} */
    private function card(string $status = 'draft'): array
    {
        $manager = $this->user('manager', 'The Manager');
        $appraiser = $this->user('manager', 'The Appraiser');
        $staff = $this->user('employee', 'The Employee');

        $employee = Employee::create([
            'user_id' => $staff->id,
            'emp_number' => 'EMP-1',
            'first_name' => 'Ian',
            'last_name' => 'Kirabo',
            'hire_date' => '2025-01-01',
            'status' => 'active',
        ]);

        $appraisal = Appraisal::create([
            'title' => 'Annual review 2026',
            'employee_id' => $employee->id,
            'year' => 2026,
            'period' => 'Annual',
            'initiated_by' => $manager->id,
            'appraiser_id' => $appraiser->id,
            'return_to_id' => $manager->id,
            'status' => $status,
        ]);

        AppraisalKpi::create([
            'appraisal_id' => $appraisal->id,
            'perspective' => 'financial',
            'kra_name' => 'Reduce operational cost',
            'performance_measure' => '3% cost reduction',
            'target' => '3%',
            'weightage' => 100,
        ]);

        return [$appraisal, $manager, $appraiser, $staff];
    }

    // ── Where the card goes when it leaves draft ─────────────────────────

    public function test_sending_a_card_puts_it_with_the_employee_not_the_appraiser(): void
    {
        [$appraisal, $manager, $appraiser] = $this->card('draft');

        $this->actingAs($manager)
            ->post(route('appraisals.send', $appraisal), ['appraiser_id' => $appraiser->id])
            ->assertRedirect();

        $appraisal->refresh();

        $this->assertSame('self_assessment', $appraisal->status);
        // The appraiser is named now but does not hold the card yet.
        $this->assertSame($appraiser->id, $appraisal->appraiser_id);
    }

    /** An employee still cannot start their own appraisal. */
    public function test_an_employee_cannot_start_an_appraisal(): void
    {
        $this->actingAs($this->user('employee'))
            ->get(route('appraisals.create'))
            ->assertForbidden();
    }

    // ── The self-assessment itself ───────────────────────────────────────

    public function test_the_employee_records_what_they_achieved_and_rates_themselves(): void
    {
        [$appraisal, , , $staff] = $this->card('self_assessment');
        $kpi = $appraisal->kpis->first();

        $this->actingAs($staff)->post(route('appraisals.self-assessment.save', $appraisal), [
            'kpi' => [$kpi->id => [
                'actual_achieved' => '4%',
                'self_rating' => 5,
                'self_note' => 'Renegotiated the two largest supplier contracts.',
            ]],
        ])->assertRedirect();

        $kpi->refresh();

        $this->assertSame('4%', $kpi->actual_achieved);
        $this->assertSame(5, $kpi->self_rating);
        $this->assertNull($kpi->rating, 'The appraiser has not scored it yet.');
    }

    /** A long card is filled in over days, so a partial save must not be refused. */
    public function test_a_partial_self_assessment_can_be_saved(): void
    {
        [$appraisal, , , $staff] = $this->card('self_assessment');
        $kpi = $appraisal->kpis->first();

        $this->actingAs($staff)->post(route('appraisals.self-assessment.save', $appraisal), [
            'kpi' => [$kpi->id => ['actual_achieved' => '4%']],
        ])->assertRedirect();

        $this->assertSame('4%', $kpi->refresh()->actual_achieved);
        $this->assertSame('self_assessment', $appraisal->refresh()->status, 'Saving is not submitting.');
    }

    public function test_nobody_else_can_fill_in_the_self_assessment(): void
    {
        [$appraisal, $manager, $appraiser] = $this->card('self_assessment');
        $kpi = $appraisal->kpis->first();

        foreach ([$manager, $appraiser] as $wrongPerson) {
            $this->actingAs($wrongPerson)
                ->post(route('appraisals.self-assessment.save', $appraisal), [
                    'kpi' => [$kpi->id => ['self_rating' => 1]],
                ])
                ->assertForbidden();
        }

        $this->assertNull($kpi->refresh()->self_rating);
    }

    /**
     * A half-answered card reaching the appraiser invites them to fill the gaps
     * themselves, which is what putting the employee first was meant to prevent.
     */
    public function test_an_incomplete_self_assessment_cannot_be_submitted(): void
    {
        [$appraisal, , , $staff] = $this->card('self_assessment');

        $this->actingAs($staff)
            ->post(route('appraisals.self-assessment.submit', $appraisal))
            ->assertSessionHas('error');

        $this->assertSame('self_assessment', $appraisal->refresh()->status);
    }

    public function test_a_complete_self_assessment_goes_to_the_appraiser(): void
    {
        [$appraisal, , , $staff] = $this->card('self_assessment');
        $appraisal->kpis()->update(['self_rating' => 4]);

        $this->actingAs($staff)
            ->post(route('appraisals.self-assessment.submit', $appraisal))
            ->assertRedirect();

        $appraisal->refresh();

        $this->assertSame('with_appraiser', $appraisal->status);
        $this->assertNotNull($appraisal->self_assessed_at);
        $this->assertNull($appraisal->employee_signed_at, 'Self-assessment is not the final signature.');
    }

    // ── The two ratings must never collide ───────────────────────────────

    /**
     * The whole reason self_rating is its own column. Two people rate the same
     * KPI and will not always agree; one shared field would destroy whichever was
     * written second, and that disagreement is the conversation.
     */
    public function test_the_appraisers_rating_does_not_overwrite_the_employees(): void
    {
        [$appraisal, , $appraiser, $staff] = $this->card('self_assessment');
        $kpi = $appraisal->kpis->first();

        $this->actingAs($staff)->post(route('appraisals.self-assessment.save', $appraisal), [
            'kpi' => [$kpi->id => ['actual_achieved' => '4%', 'self_rating' => 5]],
        ]);

        $this->actingAs($staff)->post(route('appraisals.self-assessment.submit', $appraisal));

        // The appraiser disagrees, and both views survive.
        $this->actingAs($appraiser)->post(route('appraisals.score', $appraisal), [
            'kpi' => [$kpi->id => ['actual_achieved' => '4%', 'rating' => 3]],
        ]);

        $kpi->refresh();

        $this->assertSame(5, $kpi->self_rating, "The employee's own rating must survive.");
        $this->assertSame(3, $kpi->rating, "The appraiser's rating is the official one.");
    }

    /** Only the appraiser's rating counts toward the score. */
    public function test_the_overall_score_uses_the_appraisers_rating(): void
    {
        [$appraisal, , $appraiser, $staff] = $this->card('self_assessment');
        $kpi = $appraisal->kpis->first();

        $this->actingAs($staff)->post(route('appraisals.self-assessment.save', $appraisal), [
            'kpi' => [$kpi->id => ['self_rating' => 5]],
        ]);
        $this->actingAs($staff)->post(route('appraisals.self-assessment.submit', $appraisal));

        $this->actingAs($appraiser)->post(route('appraisals.score', $appraisal), [
            'kpi' => [$kpi->id => ['rating' => 3]],
        ]);

        // One KPI at 100% weight, rated 3 of 5 = 60%.
        $this->assertEqualsWithDelta(60, (float) $appraisal->refresh()->overall_percent, 0.5);
    }

    // ── The employee's second touch still exists ─────────────────────────

    public function test_the_employee_still_signs_at_the_end(): void
    {
        [$appraisal, , , $staff] = $this->card('with_employee');

        $this->actingAs($staff)->post(route('appraisals.self', $appraisal), [
            'employee_comment' => 'Agreed, and happy with the review.',
        ])->assertRedirect();

        $appraisal->refresh();

        $this->assertSame('completed', $appraisal->status);
        $this->assertNotNull($appraisal->employee_signed_at);
    }

    /** Sign-off belongs at the end, not while the card is still with them. */
    public function test_the_employee_cannot_sign_during_self_assessment(): void
    {
        [$appraisal, , , $staff] = $this->card('self_assessment');

        $this->actingAs($staff)->post(route('appraisals.self', $appraisal), [
            'employee_comment' => 'Trying to skip ahead.',
        ])->assertForbidden();

        $this->assertSame('self_assessment', $appraisal->refresh()->status);
    }
}
