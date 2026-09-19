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
 * An appraisal card passes through four hands, and the order is the design.
 *
 *     draft            HR sets the KPIs and their weights
 *     with_appraiser   the appraiser scores what was achieved
 *     with_manager     the line manager confirms, or sends it back
 *     with_employee    the employee reads it, comments, and signs
 *     completed
 *
 * The app had been talking to `bsc/*` — the cycle-based scheme these tables
 * replaced — so nobody had noticed the handset and the browser were reading two
 * different systems. Now that the phone can move a card, the guards matter on both:
 * an appraisal scored by the wrong person, or signed before it was confirmed, is
 * worse than one that cannot be reached from a phone at all.
 *
 * Every guard asserted here is the web controller's, so the two cannot drift.
 */
class AppraisalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name = 'Someone'): User
    {
        Role::findOrCreate('employee', 'web');

        $user = User::factory()->create(['name' => $name]);
        $user->assignRole('employee');

        return $user;
    }

    private function employeeFor(User $user): Employee
    {
        return Employee::create([
            'user_id' => $user->id,
            // Required and unique. Derived from the user so several employees can
            // exist in one test without colliding.
            'emp_number' => 'EMP-'.$user->id,
            'first_name' => 'Aisha',
            'last_name' => 'Nakato',
            'hire_date' => '2024-01-15',
            'status' => 'active',
        ]);
    }

    /**
     * A card mid-flight, with the people around it.
     *
     * @return array{Appraisal, User, User, User}
     */
    private function card(string $status = 'with_appraiser'): array
    {
        $appraiser = $this->user('The Appraiser');
        $manager = $this->user('The Manager');
        $staff = $this->user('The Employee');
        $employee = $this->employeeFor($staff);

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
            'kra_name' => 'Collect outstanding invoices',
            'performance_measure' => 'Debtor days',
            'target' => '30',
            // One KPI carrying the whole card, so the overall percentage is the
            // rating and nothing else — easy to reason about when it is wrong.
            'weightage' => 100,
        ]);

        return [$appraisal, $appraiser, $manager, $staff];
    }

    public function test_only_the_appraiser_may_score(): void
    {
        [$appraisal, , $manager, $staff] = $this->card('with_appraiser');

        foreach ([$manager, $staff] as $wrongPerson) {
            $this->actingAs($wrongPerson, 'sanctum')
                ->postJson("/api/appraisals/{$appraisal->id}/score", [
                    'kpi' => [$appraisal->kpis->first()->id => ['rating' => 5]],
                ])
                ->assertForbidden();
        }

        $this->assertNull($appraisal->kpis()->first()->rating);
    }

    public function test_the_appraiser_may_score_and_may_save_a_partial_card(): void
    {
        [$appraisal, $appraiser] = $this->card('with_appraiser');
        $kpi = $appraisal->kpis->first();

        $this->actingAs($appraiser, 'sanctum')
            ->postJson("/api/appraisals/{$appraisal->id}/score", [
                'kpi' => [$kpi->id => ['actual_achieved' => '28', 'rating' => 4]],
            ])
            ->assertOk()
            ->assertJsonPath('unrated', 0);

        $this->assertSame(4, $kpi->refresh()->rating);
    }

    /**
     * An overall score computed from a subset of the weights reads as a real
     * number and is not one, so an unrated KPI stops the card being handed on.
     */
    public function test_a_card_with_an_unrated_kpi_cannot_be_returned(): void
    {
        [$appraisal, $appraiser, $manager] = $this->card('with_appraiser');

        $this->actingAs($appraiser, 'sanctum')
            ->postJson("/api/appraisals/{$appraisal->id}/return", ['return_to_id' => $manager->id])
            ->assertStatus(422);

        $this->assertSame('with_appraiser', $appraisal->refresh()->status);
    }

    public function test_a_fully_scored_card_goes_to_the_manager(): void
    {
        [$appraisal, $appraiser, $manager] = $this->card('with_appraiser');
        $appraisal->kpis()->update(['rating' => 4]);

        $this->actingAs($appraiser, 'sanctum')
            ->postJson("/api/appraisals/{$appraisal->id}/return", ['return_to_id' => $manager->id])
            ->assertOk();

        $this->assertSame('with_manager', $appraisal->refresh()->status);
    }

    public function test_the_employee_cannot_sign_before_the_manager_confirms(): void
    {
        [$appraisal, , , $staff] = $this->card('with_manager');

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/appraisals/{$appraisal->id}/self", ['employee_comment' => 'Looks fine.'])
            ->assertForbidden();

        $this->assertSame('with_manager', $appraisal->refresh()->status);
        $this->assertNull($appraisal->refresh()->employee_signed_at);
    }

    public function test_the_appraiser_cannot_confirm_their_own_scoring(): void
    {
        [$appraisal, $appraiser] = $this->card('with_manager');

        $this->actingAs($appraiser, 'sanctum')
            ->postJson("/api/appraisals/{$appraisal->id}/confirm")
            ->assertForbidden();
    }

    /** Sending back without a reason is how a card ends up bouncing twice. */
    public function test_sending_back_requires_a_reason(): void
    {
        [$appraisal, , $manager] = $this->card('with_manager');

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/appraisals/{$appraisal->id}/send-back", [])
            ->assertStatus(422);

        $this->assertSame('with_manager', $appraisal->refresh()->status);
    }

    public function test_the_manager_can_send_it_back_to_the_appraiser(): void
    {
        [$appraisal, , $manager] = $this->card('with_manager');

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/appraisals/{$appraisal->id}/send-back", ['comment' => 'The Q3 figure is wrong.'])
            ->assertOk();

        $this->assertSame('with_appraiser', $appraisal->refresh()->status);
    }

    /** The whole card, end to end, by the four people who own each step. */
    public function test_the_full_cycle_completes_and_is_signed_by_both_sides(): void
    {
        [$appraisal, $appraiser, $manager, $staff] = $this->card('with_appraiser');
        $kpi = $appraisal->kpis->first();

        $this->actingAs($appraiser, 'sanctum')
            ->postJson("/api/appraisals/{$appraisal->id}/score", [
                'kpi' => [$kpi->id => ['actual_achieved' => '26', 'rating' => 5]],
            ])->assertOk();

        $this->actingAs($appraiser, 'sanctum')
            ->postJson("/api/appraisals/{$appraisal->id}/return", ['return_to_id' => $manager->id])
            ->assertOk();

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/appraisals/{$appraisal->id}/confirm", ['manager_comment' => 'Agreed.'])
            ->assertOk();

        $appraisal->refresh();
        $this->assertSame('with_employee', $appraisal->status);
        $this->assertNotNull($appraisal->manager_signed_at);

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/appraisals/{$appraisal->id}/self", ['employee_comment' => 'Happy with this.'])
            ->assertOk();

        $appraisal->refresh();
        $this->assertSame('completed', $appraisal->status);
        $this->assertNotNull($appraisal->employee_signed_at, 'The employee signature is what closes the card.');
        $this->assertSame('Happy with this.', $appraisal->employee_comment);
    }

    /**
     * The server decides whose move it is, so the app does not keep a second,
     * quieter copy of the workflow in Dart.
     */
    public function test_the_card_says_whose_move_it_is(): void
    {
        [$appraisal, $appraiser, $manager, $staff] = $this->card('with_appraiser');

        $moveFor = fn (User $u) => $this->actingAs($u, 'sanctum')
            ->getJson("/api/appraisals/{$appraisal->id}")
            ->json('data.your_move');

        $this->assertSame('score', $moveFor($appraiser));
        $this->assertNull($moveFor($manager));
        $this->assertNull($moveFor($staff));

        $appraisal->update(['status' => 'with_employee']);
        $this->assertSame('self_appraise', $moveFor($staff));
        $this->assertNull($moveFor($appraiser));
    }

    // ── Self-assessment: the employee's first touch ──────────────────────

    /**
     * Without a case for `self_assessment`, a card sitting with the employee
     * showed no action at all on the phone — which is the one place the person
     * being appraised is most likely to be.
     */
    public function test_the_card_tells_the_employee_it_is_their_move(): void
    {
        [$appraisal, $appraiser, $manager, $staff] = $this->card('self_assessment');

        $moveFor = fn ($u) => $this->actingAs($u, 'sanctum')
            ->getJson("/api/appraisals/{$appraisal->id}")
            ->json('data.your_move');

        $this->assertSame('self_assess', $moveFor($staff));
        $this->assertNull($moveFor($appraiser), 'The appraiser has not been handed it yet.');
        $this->assertNull($moveFor($manager));
    }

    public function test_the_employee_can_record_what_they_achieved(): void
    {
        [$appraisal, , , $staff] = $this->card('self_assessment');
        $kpi = $appraisal->kpis->first();

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/appraisals/{$appraisal->id}/self-assessment", [
                'kpi' => [$kpi->id => [
                    'actual_achieved' => '26',
                    'self_rating' => 5,
                    'self_note' => 'Cleared the backlog in August.',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('unrated', 0);

        $kpi->refresh();

        $this->assertSame(5, $kpi->self_rating);
        $this->assertNull($kpi->rating, 'The appraiser has not scored it yet.');
    }

    public function test_the_appraiser_cannot_fill_in_the_self_assessment(): void
    {
        [$appraisal, $appraiser] = $this->card('self_assessment');
        $kpi = $appraisal->kpis->first();

        $this->actingAs($appraiser, 'sanctum')
            ->postJson("/api/appraisals/{$appraisal->id}/self-assessment", [
                'kpi' => [$kpi->id => ['self_rating' => 1]],
            ])
            ->assertForbidden();

        $this->assertNull($kpi->refresh()->self_rating);
    }

    public function test_an_incomplete_self_assessment_cannot_be_submitted(): void
    {
        [$appraisal, , , $staff] = $this->card('self_assessment');

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/appraisals/{$appraisal->id}/self-assessment/submit")
            ->assertStatus(422);

        $this->assertSame('self_assessment', $appraisal->refresh()->status);
    }

    public function test_a_complete_self_assessment_moves_the_card_to_the_appraiser(): void
    {
        [$appraisal, , , $staff] = $this->card('self_assessment');
        $appraisal->kpis()->update(['self_rating' => 4]);

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/appraisals/{$appraisal->id}/self-assessment/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'with_appraiser');

        $this->assertNotNull($appraisal->refresh()->self_assessed_at);
    }

    /** Both ratings travel with the card, so the app can show the gap. */
    public function test_the_card_carries_both_ratings(): void
    {
        [$appraisal, $appraiser, , $staff] = $this->card('self_assessment');
        $kpi = $appraisal->kpis->first();

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/appraisals/{$appraisal->id}/self-assessment", [
                'kpi' => [$kpi->id => ['self_rating' => 5]],
            ])->assertOk();

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/appraisals/{$appraisal->id}/self-assessment/submit")->assertOk();

        $this->actingAs($appraiser, 'sanctum')
            ->postJson("/api/appraisals/{$appraisal->id}/score", [
                'kpi' => [$kpi->id => ['rating' => 3]],
            ])->assertOk();

        $row = $this->actingAs($staff, 'sanctum')
            ->getJson("/api/appraisals/{$appraisal->id}")
            ->json('data.kpis.0');

        $this->assertSame(5, $row['self_rating']);
        $this->assertSame(3, $row['rating']);
    }

    public function test_a_stranger_cannot_read_somebody_elses_appraisal(): void
    {
        [$appraisal] = $this->card('with_appraiser');

        $this->actingAs($this->user('Nobody'), 'sanctum')
            ->getJson("/api/appraisals/{$appraisal->id}")
            ->assertForbidden();
    }
}
