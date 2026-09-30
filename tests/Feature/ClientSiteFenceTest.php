<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Client;
use App\Models\ClientSite;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A client that is more than one place.
 *
 * `clients.work_site_lat`/`work_site_lng` hold a single point, which is fine for a
 * hotel and wrong for the two clients that matter most:
 *
 *     Roofings Uganda Limited   439 staff   Lubowa (259), Industrial Area (180)
 *     Mastermind Consult Ltd HQ  29 staff   Head Office (15), Field Staff (14)
 *
 * Roofings' premises are roughly ten kilometres apart. Whichever single point were
 * set, the other workforce would be permanently outside the fence — so setting a
 * coordinate would have started refusing real staff standing at the right gate.
 */
class ClientSiteFenceTest extends TestCase
{
    use RefreshDatabase;

    /** Roughly Lubowa and the Kampala industrial area — about 9 km apart. */
    private const LUBOWA = [0.2489, 32.5561];
    private const INDUSTRIAL = [0.3136, 32.5985];

    private function client(?float $legacyLat = null, ?float $legacyLng = null): Client
    {
        return Client::create([
            'user_id' => User::factory()->create()->id,
            'company_name' => 'Roofings Uganda Limited',
            'contact_person' => 'Brenda Kansiime',
            'status' => 'active',
            'work_site_lat' => $legacyLat,
            'work_site_lng' => $legacyLng,
            'geo_fence_radius' => 100,
            'attendance_enabled' => true,
        ]);
    }

    private function site(Client $client, string $name, array $point, int $radius = 100): ClientSite
    {
        return ClientSite::create([
            'client_id' => $client->id,
            'name' => $name,
            'lat' => $point[0],
            'lng' => $point[1],
            'geo_fence_radius' => $radius,
            'is_active' => true,
        ]);
    }

    private function staff(Client $client): User
    {
        static $n = 0;
        $n++;

        Role::findOrCreate('employee', 'web');
        $user = User::factory()->create();
        $user->assignRole('employee');

        $employee = Employee::create([
            'user_id' => $user->id,
            'emp_number' => 'RUL-'.$n,
            'first_name' => 'Site',
            'last_name' => 'Worker '.$n,
            'hire_date' => '2026-01-01',
            'status' => 'active',
        ]);

        $client->employees()->attach($employee->id, ['assigned_by' => $user->id]);

        return $user;
    }

    private function clockIn(User $user, array $point)
    {
        return $this->actingAs($user, 'sanctum')->postJson('/api/attendance/clock-in', [
            'latitude' => $point[0],
            'longitude' => $point[1],
        ]);
    }

    // ── The case a single coordinate cannot express ──────────────────────

    public function test_staff_at_either_site_can_clock_in(): void
    {
        $client = $this->client();
        $this->site($client, 'Lubowa', self::LUBOWA);
        $this->site($client, 'Industrial Area', self::INDUSTRIAL);

        $atLubowa = $this->staff($client);
        $atIndustrial = $this->staff($client);

        $this->clockIn($atLubowa, self::LUBOWA)->assertCreated();
        $this->clockIn($atIndustrial, self::INDUSTRIAL)->assertCreated();

        $this->assertSame(
            2,
            AttendanceLog::where('location_status', AttendanceLog::LOCATION_VERIFIED)->count(),
            'Both workforces should be verified against their own site.'
        );
    }

    /**
     * The old behaviour, stated as a test so it cannot come back: with only one
     * of the two sites configured, half the workforce is refused.
     */
    public function test_with_only_one_site_the_other_workforce_is_flagged(): void
    {
        $client = $this->client();
        $this->site($client, 'Lubowa', self::LUBOWA);

        $this->clockIn($this->staff($client), self::INDUSTRIAL)
            ->assertCreated()
            ->assertJsonPath('off_site', true);

        // Still on the record: the half of the workforce whose site nobody has
        // mapped yet must not lose their day over it.
        $this->assertSame(AttendanceLog::LOCATION_OUTSIDE, AttendanceLog::latest('id')->first()->location_status);
    }

    /** Measured against the nearest site, not the first one created. */
    public function test_distance_is_measured_to_the_nearest_site(): void
    {
        $client = $this->client();
        $this->site($client, 'Lubowa', self::LUBOWA);
        $this->site($client, 'Industrial Area', self::INDUSTRIAL);

        $this->clockIn($this->staff($client), self::INDUSTRIAL)->assertCreated();

        $log = AttendanceLog::latest('id')->first();

        $this->assertLessThan(
            150,
            (float) $log->distance_metres,
            'Standing at Industrial Area should read as metres from it, not kilometres from Lubowa.'
        );
    }

    /** The notice names the site, because the client name alone is baffling. */
    public function test_the_notice_names_the_site_it_measured_against(): void
    {
        $client = $this->client();
        $this->site($client, 'Lubowa', self::LUBOWA);

        $response = $this->clockIn($this->staff($client), self::INDUSTRIAL)->assertCreated();

        // "9km from Lubowa" is actionable where "9km from Roofings Uganda
        // Limited" means nothing to somebody standing at Industrial Area.
        $this->assertStringContainsString('Lubowa', $response->json('location_notice'));
    }

    /** Each site carries its own radius: a factory compound is not a front desk. */
    public function test_each_site_has_its_own_radius(): void
    {
        $client = $this->client();

        // 2 km radius — a large industrial compound.
        $this->site($client, 'Industrial Area', self::INDUSTRIAL, radius: 2000);

        // About 700 m from the centre: outside a 100 m fence, inside a 2 km one.
        $nearby = [self::INDUSTRIAL[0] + 0.0063, self::INDUSTRIAL[1]];

        $this->clockIn($this->staff($client), $nearby)->assertCreated();

        $this->assertSame(
            AttendanceLog::LOCATION_VERIFIED,
            AttendanceLog::latest('id')->first()->location_status
        );
    }

    /** A retired site stops counting as being on site. */
    public function test_an_inactive_site_does_not_verify_anybody(): void
    {
        $client = $this->client();
        $this->site($client, 'Lubowa', self::LUBOWA);
        $this->site($client, 'Old depot', self::INDUSTRIAL)->update(['is_active' => false]);

        $this->clockIn($this->staff($client), self::INDUSTRIAL)->assertCreated();

        $this->assertSame(
            AttendanceLog::LOCATION_OUTSIDE,
            AttendanceLog::latest('id')->first()->location_status,
            'Standing at a retired depot is not standing at a work site.'
        );
    }

    // ── Not breaking what already worked ─────────────────────────────────

    /**
     * A client that never had sites added keeps behaving exactly as before,
     * measured against its single legacy coordinate.
     */
    public function test_a_client_with_no_sites_falls_back_to_its_old_coordinates(): void
    {
        $client = $this->client(legacyLat: self::LUBOWA[0], legacyLng: self::LUBOWA[1]);

        $this->assertTrue($client->hasGeoFence());

        $this->clockIn($this->staff($client), self::LUBOWA)->assertCreated();

        $this->assertSame(
            AttendanceLog::LOCATION_VERIFIED,
            AttendanceLog::latest('id')->first()->location_status
        );
    }

    public function test_the_fallback_still_flags_somebody_far_away(): void
    {
        $client = $this->client(legacyLat: self::LUBOWA[0], legacyLng: self::LUBOWA[1]);

        $this->clockIn($this->staff($client), self::INDUSTRIAL)->assertCreated();

        $this->assertSame(AttendanceLog::LOCATION_OUTSIDE, AttendanceLog::latest('id')->first()->location_status);
    }

    /** Sites win over the legacy pair once any exist. */
    public function test_sites_supersede_the_legacy_coordinates(): void
    {
        $client = $this->client(legacyLat: self::LUBOWA[0], legacyLng: self::LUBOWA[1]);
        $this->site($client, 'Industrial Area', self::INDUSTRIAL);

        // Lubowa is now only the retired legacy point, and is not a site.
        $this->clockIn($this->staff($client), self::LUBOWA)
            ->assertCreated()->assertJsonPath('off_site', true);
        $this->clockIn($this->staff($client), self::INDUSTRIAL)
            ->assertCreated()->assertJsonPath('off_site', false);
    }

    /** Nothing configured at all is still permitted, and still says so. */
    public function test_a_client_with_nothing_configured_is_unfenced(): void
    {
        $client = $this->client();

        $this->assertFalse($client->hasGeoFence());

        $this->clockIn($this->staff($client), self::LUBOWA)->assertCreated();

        $this->assertSame(
            AttendanceLog::LOCATION_UNFENCED,
            AttendanceLog::latest('id')->first()->location_status
        );
    }
}
