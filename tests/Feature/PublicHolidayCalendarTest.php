<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\HolidayPayApproval;
use App\Models\HolidayWork;
use App\Models\PublicHoliday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The public holiday calendar, which feeds pay in two directions.
 *
 * Monthly staff have holidays counted toward their worked days, so a missing
 * holiday shortens the month and underpays them. Casual staff earn double for an
 * approved holiday they worked.
 *
 * The delete guard is the test that matters most. `holiday_pay_approvals` and
 * `holiday_work` both cascade, so before this guard existed, removing a holiday
 * that had been used erased the pay decision and the attendance record with it —
 * silently, leaving a payslip that paid somebody double for a day the system no
 * longer had.
 */
class PublicHolidayCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function holiday(string $date, string $name = 'Independence Day'): PublicHoliday
    {
        return PublicHoliday::create([
            'date' => $date,
            'name' => $name,
            'type' => 'national',
            'year' => (int) substr($date, 0, 4),
            'is_paid' => true,
            'country' => 'UG',
        ]);
    }

    // ── Reading ──────────────────────────────────────────────────────────

    public function test_the_calendar_is_listed_for_one_year_in_date_order(): void
    {
        $this->holiday('2026-12-25', 'Christmas Day');
        $this->holiday('2026-01-01', "New Year's Day");
        $this->holiday('2025-01-01', 'Last year');

        $this->actingAs($this->user('hr-admin'), 'sanctum')
            ->getJson('/api/public-holidays?year=2026')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', "New Year's Day")
            ->assertJsonPath('data.1.name', 'Christmas Day');
    }

    /** A holiday on a Saturday changes nothing for most people, and says so. */
    public function test_a_weekend_holiday_is_flagged(): void
    {
        // 2026-10-09 is a Friday; 2026-10-10 a Saturday.
        $this->holiday('2026-10-09', 'Independence Day');
        $this->holiday('2026-10-10', 'Some Saturday');

        $response = $this->actingAs($this->user('hr-admin'), 'sanctum')
            ->getJson('/api/public-holidays?year=2026')->assertOk();

        $byName = collect($response->json('data'))->keyBy('name');

        $this->assertFalse($byName['Independence Day']['falls_on_weekend']);
        $this->assertTrue($byName['Some Saturday']['falls_on_weekend']);
    }

    public function test_an_employee_cannot_change_the_calendar(): void
    {
        $this->actingAs($this->user('employee'), 'sanctum')
            ->postJson('/api/public-holidays', [
                'date' => '2026-05-01', 'name' => 'Labour Day', 'type' => 'national',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('public_holidays', 0);
    }

    // ── Adding ───────────────────────────────────────────────────────────

    public function test_hr_can_add_a_holiday_and_the_year_is_derived_from_the_date(): void
    {
        $this->actingAs($this->user('hr-admin'), 'sanctum')
            ->postJson('/api/public-holidays', [
                'date' => '2027-06-03',
                'name' => "Martyrs' Day",
                'type' => 'national',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('public_holidays', [
            'name' => "Martyrs' Day",
            'year' => 2027,
        ]);
    }

    /** Two holidays on one day is a duplicate, not two events. */
    public function test_entering_the_same_date_twice_corrects_it_rather_than_duplicating(): void
    {
        $hr = $this->user('hr-admin');

        $this->actingAs($hr, 'sanctum')->postJson('/api/public-holidays', [
            'date' => '2026-06-09', 'name' => 'Heroes Day', 'type' => 'national',
        ])->assertCreated();

        $this->actingAs($hr, 'sanctum')->postJson('/api/public-holidays', [
            'date' => '2026-06-09', 'name' => "National Heroes' Day", 'type' => 'national',
        ])->assertCreated();

        $this->assertDatabaseCount('public_holidays', 1);
        $this->assertDatabaseHas('public_holidays', ['name' => "National Heroes' Day"]);
    }

    // ── Seeding a year ───────────────────────────────────────────────────

    public function test_seeding_fills_in_the_fixed_holidays(): void
    {
        $this->actingAs($this->user('hr-admin'), 'sanctum')
            ->postJson('/api/public-holidays/seed', ['year' => 2027])
            ->assertOk()
            ->assertJsonPath('data.added', 9);

        $this->assertDatabaseHas('public_holidays', ['year' => 2027, 'name' => 'Christmas Day']);
        $this->assertDatabaseHas('public_holidays', ['year' => 2027, 'name' => 'Independence Day']);
    }

    /** Pressing it twice changes nothing. */
    public function test_seeding_is_idempotent(): void
    {
        $hr = $this->user('hr-admin');

        $this->actingAs($hr, 'sanctum')
            ->postJson('/api/public-holidays/seed', ['year' => 2027])->assertOk();

        $this->actingAs($hr, 'sanctum')
            ->postJson('/api/public-holidays/seed', ['year' => 2027])
            ->assertOk()
            ->assertJsonPath('data.added', 0);

        $this->assertSame(9, PublicHoliday::where('year', 2027)->count());
    }

    /** A half-filled year is completed without disturbing what is there. */
    public function test_seeding_completes_a_partly_entered_year(): void
    {
        $this->holiday('2027-12-25', 'Christmas (entered by hand)');

        $this->actingAs($this->user('hr-admin'), 'sanctum')
            ->postJson('/api/public-holidays/seed', ['year' => 2027])
            ->assertOk()
            ->assertJsonPath('data.added', 8);

        // The hand-entered name is left alone.
        $this->assertDatabaseHas('public_holidays', ['name' => 'Christmas (entered by hand)']);
    }

    /**
     * The moving feasts are not guessed. Putting a wrong Eid date into a calendar
     * that decides pay would be worse than leaving it out.
     */
    public function test_seeding_says_which_holidays_it_cannot_know(): void
    {
        $response = $this->actingAs($this->user('hr-admin'), 'sanctum')
            ->postJson('/api/public-holidays/seed', ['year' => 2027])
            ->assertOk();

        $this->assertStringContainsString('Eid', $response->json('data.note'));
        $this->assertStringContainsString('Easter', $response->json('data.note'));
    }

    // ── Deleting ─────────────────────────────────────────────────────────

    public function test_an_unused_holiday_can_be_deleted(): void
    {
        $holiday = $this->holiday('2026-03-08', "Women's Day");

        $this->actingAs($this->user('hr-admin'), 'sanctum')
            ->deleteJson("/api/public-holidays/{$holiday->id}")
            ->assertOk();

        $this->assertDatabaseCount('public_holidays', 0);
    }

    /**
     * The guard that matters. A pay decision cascades away with the holiday.
     */
    public function test_a_holiday_with_a_pay_decision_cannot_be_deleted(): void
    {
        $holiday = $this->holiday('2026-10-09');

        HolidayPayApproval::create([
            'public_holiday_id' => $holiday->id,
            'status' => 'approved',
            'decided_by' => $this->user('hr-admin')->id,
            'decided_at' => now(),
        ]);

        $this->actingAs($this->user('hr-admin'), 'sanctum')
            ->deleteJson("/api/public-holidays/{$holiday->id}")
            ->assertStatus(422);

        $this->assertDatabaseCount('public_holidays', 1);
        $this->assertDatabaseCount('holiday_pay_approvals', 1);
    }

    /** And so does the record of who worked it. */
    public function test_a_holiday_somebody_worked_cannot_be_deleted(): void
    {
        $holiday = $this->holiday('2026-10-09');

        $employee = Employee::create([
            'emp_number' => 'EMP-1',
            'first_name' => 'Joan',
            'last_name' => 'Akello',
            'hire_date' => '2025-01-01',
            'status' => 'active',
        ]);

        HolidayWork::create([
            'public_holiday_id' => $holiday->id,
            'employee_id' => $employee->id,
            'worked' => true,
        ]);

        $this->actingAs($this->user('hr-admin'), 'sanctum')
            ->deleteJson("/api/public-holidays/{$holiday->id}")
            ->assertStatus(422);

        $this->assertDatabaseCount('public_holidays', 1);
        $this->assertDatabaseCount('holiday_work', 1);
    }

    /** The listing says up front which rows are still removable. */
    public function test_the_listing_reports_whether_a_holiday_can_still_be_deleted(): void
    {
        $used = $this->holiday('2026-10-09', 'Independence Day');
        $this->holiday('2026-12-25', 'Christmas Day');

        HolidayPayApproval::create([
            'public_holiday_id' => $used->id,
            'status' => 'approved',
            'decided_by' => $this->user('hr-admin')->id,
            'decided_at' => now(),
        ]);

        $response = $this->actingAs($this->user('hr-admin'), 'sanctum')
            ->getJson('/api/public-holidays?year=2026')->assertOk();

        $byName = collect($response->json('data'))->keyBy('name');

        $this->assertFalse($byName['Independence Day']['can_delete']);
        $this->assertTrue($byName['Christmas Day']['can_delete']);
    }
}
