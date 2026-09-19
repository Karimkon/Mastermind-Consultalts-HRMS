<?php

namespace Tests\Feature;

use App\Models\SalaryComponent;
use App\Models\SalaryGrade;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Shifts, salary grades and salary components.
 *
 * Configuration rather than daily work, but two of the three are inputs to every
 * payslip, so the tests here are about who may change them and what must not be
 * changeable at all.
 *
 * The component rules carry the most weight. `PayrollService` recognises the
 * statutory deductions by code and by nothing else — `NSSF_EMP` is the employee's
 * 5%, `NSSF_CO` the employer's 10% — so a component saved without a code is not
 * merely untidy, it is invisible to payroll.
 */
class ConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    // ── Shifts ───────────────────────────────────────────────────────────

    public function test_anybody_signed_in_can_read_the_shifts(): void
    {
        Shift::create(['name' => 'Day', 'start_time' => '08:00', 'end_time' => '17:00', 'grace_minutes' => 10]);

        $this->actingAs($this->user('employee'), 'sanctum')
            ->getJson('/api/shifts')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Day')
            ->assertJsonPath('data.0.grace_minutes', 10)
            // A supervisor may look without being offered the ability to change.
            ->assertJsonPath('can_configure', false);
    }

    public function test_an_employee_cannot_create_a_shift(): void
    {
        $this->actingAs($this->user('employee'), 'sanctum')
            ->postJson('/api/shifts', ['name' => 'Night', 'start_time' => '20:00', 'end_time' => '04:00'])
            ->assertForbidden();

        $this->assertDatabaseCount('shifts', 0);
    }

    public function test_hr_can_create_a_shift(): void
    {
        $this->actingAs($this->user('hr-admin'), 'sanctum')
            ->postJson('/api/shifts', [
                'name' => 'Night',
                'start_time' => '20:00',
                'end_time' => '04:00',
                'grace_minutes' => 15,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('shifts', ['name' => 'Night', 'grace_minutes' => 15]);
    }

    /** A shift ending before it starts runs through midnight, and says so. */
    public function test_a_night_shift_is_reported_as_crossing_midnight(): void
    {
        Shift::create(['name' => 'Night', 'start_time' => '20:00', 'end_time' => '04:00']);
        Shift::create(['name' => 'Day', 'start_time' => '08:00', 'end_time' => '17:00']);

        $response = $this->actingAs($this->user('hr-admin'), 'sanctum')
            ->getJson('/api/shifts')
            ->assertOk();

        $byName = collect($response->json('data'))->keyBy('name');

        $this->assertTrue($byName['Night']['crosses_midnight']);
        $this->assertFalse($byName['Day']['crosses_midnight']);
    }

    public function test_an_absurd_grace_period_is_refused(): void
    {
        $this->actingAs($this->user('hr-admin'), 'sanctum')
            ->postJson('/api/shifts', [
                'name' => 'Day',
                'start_time' => '08:00',
                'end_time' => '17:00',
                // Two hours of forgiven lateness is a typo, not a policy.
                'grace_minutes' => 240,
            ])
            ->assertStatus(422);
    }

    // ── Salary grades ────────────────────────────────────────────────────

    public function test_hr_can_create_a_grade(): void
    {
        $this->actingAs($this->user('hr-admin'), 'sanctum')
            ->postJson('/api/salary-grades', [
                'grade' => 'G4',
                'basic_min' => 800_000,
                'basic_max' => 1_200_000,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('salary_grades', ['grade' => 'G4']);
    }

    /**
     * A band whose ceiling sits below its floor cannot contain anybody, and the
     * failure would only surface when somebody was placed in it.
     */
    public function test_a_grade_cannot_end_below_where_it_starts(): void
    {
        $this->actingAs($this->user('hr-admin'), 'sanctum')
            ->postJson('/api/salary-grades', [
                'grade' => 'Broken',
                'basic_min' => 1_200_000,
                'basic_max' => 800_000,
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('salary_grades', 0);
    }

    public function test_grades_come_back_in_order(): void
    {
        SalaryGrade::create(['grade' => 'G5', 'label' => 'Lead', 'basic_min' => 1_500_000, 'basic_max' => 2_000_000]);
        SalaryGrade::create(['grade' => 'G1', 'label' => 'Junior', 'basic_min' => 300_000, 'basic_max' => 500_000]);

        $this->actingAs($this->user('employee'), 'sanctum')
            ->getJson('/api/salary-grades')
            ->assertOk()
            ->assertJsonPath('data.0.grade', 'G1')
            // The label the form posts is now kept. It used not to be fillable,
            // so every grade was saved with an empty one.
            ->assertJsonPath('data.0.label', 'Junior');
    }

    // ── Salary components ────────────────────────────────────────────────

    /**
     * The bug this module was built on top of.
     *
     * The web controller validated only name and type and then called create()
     * without `code`. The column is NOT NULL with no default, so every attempt to
     * add a component died on the insert — and had done since the screen existed.
     */
    public function test_a_component_cannot_be_created_without_a_code(): void
    {
        $this->actingAs($this->user('hr-admin'), 'sanctum')
            ->postJson('/api/salary-components', [
                'name' => 'Housing Allowance',
                'type' => 'allowance',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_hr_can_create_a_component_and_it_is_active(): void
    {
        $this->actingAs($this->user('hr-admin'), 'sanctum')
            ->postJson('/api/salary-components', [
                'name' => 'Housing Allowance',
                'code' => 'hra',
                'type' => 'allowance',
                'is_fixed' => true,
                'amount' => 200_000,
            ])
            ->assertCreated();

        // Upper-cased on the way in, because PayrollService compares codes exactly.
        $this->assertDatabaseHas('salary_components', [
            'code' => 'HRA',
            'is_active' => true,
        ]);
    }

    public function test_two_components_cannot_share_a_code(): void
    {
        SalaryComponent::create([
            'name' => 'Housing', 'code' => 'HRA', 'type' => 'allowance',
            'is_fixed' => true, 'amount' => 100_000, 'is_active' => true,
        ]);

        $this->actingAs($this->user('hr-admin'), 'sanctum')
            ->postJson('/api/salary-components', [
                'name' => 'Housing (new)', 'code' => 'HRA', 'type' => 'allowance',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_the_statutory_components_are_flagged_as_such(): void
    {
        SalaryComponent::create([
            'name' => 'NSSF (Employee 5%)', 'code' => 'NSSF_EMP', 'type' => 'deduction',
            'is_fixed' => false, 'percentage' => 5, 'is_active' => true,
        ]);
        SalaryComponent::create([
            'name' => 'Transport', 'code' => 'TRANS', 'type' => 'allowance',
            'is_fixed' => true, 'amount' => 50_000, 'is_active' => true,
        ]);

        $response = $this->actingAs($this->user('hr-admin'), 'sanctum')
            ->getJson('/api/salary-components')
            ->assertOk();

        $byCode = collect($response->json('data'))->keyBy('code');

        $this->assertTrue($byCode['NSSF_EMP']['is_statutory']);
        $this->assertFalse($byCode['TRANS']['is_statutory']);
    }

    /**
     * Switching off NSSF stops a legally required deduction. Doing it from a
     * phone by accident is not a risk worth carrying for the convenience.
     */
    public function test_nssf_cannot_be_switched_off_from_the_app(): void
    {
        $nssf = SalaryComponent::create([
            'name' => 'NSSF (Employee 5%)', 'code' => 'NSSF_EMP', 'type' => 'deduction',
            'is_fixed' => false, 'percentage' => 5, 'is_active' => true,
        ]);

        $this->actingAs($this->user('hr-admin'), 'sanctum')
            ->postJson("/api/salary-components/{$nssf->id}/active", ['is_active' => false])
            ->assertStatus(422);

        $this->assertTrue((bool) $nssf->refresh()->is_active);
    }

    public function test_an_ordinary_component_can_be_switched_off_and_on(): void
    {
        $component = SalaryComponent::create([
            'name' => 'Transport', 'code' => 'TRANS', 'type' => 'allowance',
            'is_fixed' => true, 'amount' => 50_000, 'is_active' => true,
        ]);

        $hr = $this->user('hr-admin');

        $this->actingAs($hr, 'sanctum')
            ->postJson("/api/salary-components/{$component->id}/active", ['is_active' => false])
            ->assertOk();

        $this->assertFalse((bool) $component->refresh()->is_active);

        $this->actingAs($hr, 'sanctum')
            ->postJson("/api/salary-components/{$component->id}/active", ['is_active' => true])
            ->assertOk();

        $this->assertTrue((bool) $component->refresh()->is_active);
    }

    /**
     * The MD signs payroll off and does not set its inputs. Matching the web,
     * where the same restriction is spelled out as denyMd().
     */
    public function test_the_md_does_not_configure_payroll_inputs(): void
    {
        $md = $this->user('md');

        $this->actingAs($md, 'sanctum')
            ->postJson('/api/salary-components', ['name' => 'X', 'code' => 'X1', 'type' => 'allowance'])
            ->assertForbidden();

        $this->actingAs($md, 'sanctum')
            ->postJson('/api/salary-grades', ['grade' => 'G9', 'basic_min' => 1, 'basic_max' => 2])
            ->assertForbidden();
    }
}
