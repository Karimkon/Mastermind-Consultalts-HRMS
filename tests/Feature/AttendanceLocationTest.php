<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Client;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Whether a clock-in's location was actually checked, and whether the record
 * admits it when it was not.
 *
 * The geo-fence works and does block. It had never run once in production,
 * because twelve of the thirteen clients have no work_site_lat/work_site_lng and
 * the check opens by returning null when they are missing. Missing coordinates
 * meant "permitted", silently — and because that early return comes before the
 * "location is required" branch, GPS was not even demanded. All 66 attendance
 * rows looked exactly like verified on-site clock-ins. None of them was one.
 *
 * The fix is not to start blocking: that would halt attendance for 906 people on
 * the first morning. It is to stop the record from lying about what was
 * established.
 */
class AttendanceLocationTest extends TestCase
{
    use RefreshDatabase;

    /** Kampala, roughly the Roofings industrial area. */
    private const SITE_LAT = 0.3136;
    private const SITE_LNG = 32.5811;

    private function staff(?Client $client = null): User
    {
        static $n = 0;
        $n++;

        Role::findOrCreate('employee', 'web');
        $user = User::factory()->create();
        $user->assignRole('employee');

        $employee = Employee::create([
            'user_id' => $user->id,
            'emp_number' => 'EMP-'.$n,
            'first_name' => 'Site',
            'last_name' => 'Worker '.$n,
            'hire_date' => '2025-01-01',
            'status' => 'active',
        ]);

        $client?->employees()->attach($employee->id, ['assigned_by' => $user->id]);

        return $user;
    }

    private function client(?float $lat, ?float $lng, int $radius = 100): Client
    {
        return Client::create([
            'user_id' => User::factory()->create()->id,
            'company_name' => 'Roofings Uganda Limited',
            'contact_person' => 'Brenda Kansiime',
            'status' => 'active',
            'work_site_lat' => $lat,
            'work_site_lng' => $lng,
            'geo_fence_radius' => $radius,
            'attendance_enabled' => true,
        ]);
    }

    private function clockIn(User $user, ?float $lat, ?float $lng)
    {
        return $this->actingAs($user, 'sanctum')->postJson('/api/attendance/clock-in', [
            'latitude' => $lat,
            'longitude' => $lng,
        ]);
    }

    // ── When the fence can do its job ────────────────────────────────────

    public function test_a_clock_in_inside_the_fence_is_recorded_as_verified(): void
    {
        $client = $this->client(self::SITE_LAT, self::SITE_LNG);
        $user = $this->staff($client);

        $this->clockIn($user, self::SITE_LAT, self::SITE_LNG)->assertCreated();

        $log = AttendanceLog::latest('id')->first();

        $this->assertSame(AttendanceLog::LOCATION_VERIFIED, $log->location_status);
        $this->assertTrue($log->locationWasChecked());
        $this->assertNotNull($log->distance_metres);
    }

    public function test_a_clock_in_outside_the_fence_is_still_refused(): void
    {
        $client = $this->client(self::SITE_LAT, self::SITE_LNG);
        $user = $this->staff($client);

        // Roughly 11 km north — well outside a 100 m radius.
        $this->clockIn($user, self::SITE_LAT + 0.1, self::SITE_LNG)
            ->assertStatus(422)
            ->assertJsonPath('geo_error', true);

        $this->assertSame(0, AttendanceLog::count(), 'Nothing should be recorded for a refused clock-in.');
    }

    /** With a fence configured, a device that gives no fix is refused. */
    public function test_a_fenced_client_requires_a_location(): void
    {
        $client = $this->client(self::SITE_LAT, self::SITE_LNG);
        $user = $this->staff($client);

        $this->clockIn($user, null, null)->assertStatus(422);
        $this->assertSame(0, AttendanceLog::count());
    }

    // ── When it cannot ───────────────────────────────────────────────────

    /**
     * The case that describes every client but one. The clock-in is allowed —
     * blocking would stop attendance dead — but the row says it was never
     * verified rather than looking like an on-site arrival.
     */
    public function test_an_unmapped_client_records_the_clock_in_as_unverified(): void
    {
        $client = $this->client(null, null);
        $user = $this->staff($client);

        $this->clockIn($user, self::SITE_LAT, self::SITE_LNG)->assertCreated();

        $log = AttendanceLog::latest('id')->first();

        $this->assertSame(AttendanceLog::LOCATION_UNFENCED, $log->location_status);
        $this->assertFalse($log->locationWasChecked());
    }

    /**
     * The distance was previously computed against a NULL work_site_lat cast to
     * 0.0, which measured to Null Island and stored roughly three thousand
     * kilometres as though it meant something.
     */
    public function test_an_unmapped_client_stores_no_distance_rather_than_a_meaningless_one(): void
    {
        $client = $this->client(null, null);
        $user = $this->staff($client);

        $this->clockIn($user, self::SITE_LAT, self::SITE_LNG)->assertCreated();

        $this->assertNull(
            AttendanceLog::latest('id')->first()->distance_metres,
            'Distance to a work site that has no coordinates is not a number.'
        );
    }

    /** The coordinates are still kept; only their meaning is withheld. */
    public function test_an_unmapped_client_still_stores_the_coordinates(): void
    {
        $client = $this->client(null, null);
        $user = $this->staff($client);

        $this->clockIn($user, self::SITE_LAT, self::SITE_LNG)->assertCreated();

        $log = AttendanceLog::latest('id')->first();

        $this->assertEqualsWithDelta(self::SITE_LAT, (float) $log->lat, 0.0001);
        $this->assertEqualsWithDelta(self::SITE_LNG, (float) $log->lng, 0.0001);
    }

    /** No client, no fix — the shape of Brenda's row on 19 September. */
    public function test_a_clock_in_with_no_location_at_all_is_marked_no_fix(): void
    {
        $user = $this->staff();

        $this->clockIn($user, null, null)->assertCreated();

        $log = AttendanceLog::latest('id')->first();

        $this->assertSame(AttendanceLog::LOCATION_NO_FIX, $log->location_status);
        $this->assertFalse($log->locationWasChecked());
    }

    // ── What the rest of the system sees ─────────────────────────────────

    public function test_the_api_says_whether_the_location_was_checked(): void
    {
        $client = $this->client(null, null);
        $user = $this->staff($client);

        $this->clockIn($user, self::SITE_LAT, self::SITE_LNG)->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.location_status', AttendanceLog::LOCATION_UNFENCED)
            ->assertJsonPath('data.location_checked', false);
    }

    /**
     * Only two of the four statuses are evidence of anything. A report that
     * treats the other two as on-site is reporting a control that did not run.
     */
    public function test_only_a_checked_status_counts_as_evidence(): void
    {
        $checked = [AttendanceLog::LOCATION_VERIFIED, AttendanceLog::LOCATION_OUTSIDE];
        $notChecked = [AttendanceLog::LOCATION_UNFENCED, AttendanceLog::LOCATION_NO_FIX, null];

        foreach ($checked as $status) {
            $log = new AttendanceLog(['location_status' => $status]);
            $this->assertTrue($log->locationWasChecked(), "{$status} should count as checked.");
        }

        foreach ($notChecked as $status) {
            $log = new AttendanceLog(['location_status' => $status]);
            $this->assertFalse($log->locationWasChecked(), ($status ?? 'null').' should not count as checked.');
        }
    }
}
