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
 * Head office staff work wherever the clients are.
 *
 * Attendance measures a clock-in against the premises of the employee's own
 * client. For somebody posted to one site that is exactly right. For the HQ
 * staff who spend the week visiting clients it was wrong every single time:
 * standing at a client, measured against head office, recorded as off-site and
 * sent to HR as an exception - for doing the job they were sent to do.
 *
 * Clients flagged `is_head_office` are now measured against every mapped
 * premises in the system. Everyone else is unchanged, which is the half of this
 * that matters most: attendance_logs feeds payroll.
 */
class HeadOfficeRoamingAttendanceTest extends TestCase
{
    use RefreshDatabase;

    /** Roughly Kampala; far enough apart to be unambiguous. */
    private const HQ = [0.3695844, 32.5981736];
    private const LUBOWA = [0.2489000, 32.5561000];
    private const INDUSTRIAL_AREA = [0.3136000, 32.5985000];
    private const NOWHERE = [1.5000000, 33.9000000];

    private function client(string $name, array $coords, bool $headOffice = false, int $radius = 500): Client
    {
        // clients.user_id is NOT NULL: every client carries its portal login.
        $portal = User::create([
            'name' => $name . ' portal',
            'email' => \Illuminate\Support\Str::slug($name) . '-portal@test.local',
            'password' => bcrypt('secret'),
        ]);

        $client = Client::create([
            'user_id' => $portal->id,
            'company_name' => $name,
            'contact_person' => 'Contact Person',
            'status' => 'active',
            'attendance_enabled' => true,
            'is_head_office' => $headOffice,
        ]);

        ClientSite::create([
            'client_id' => $client->id,
            'name' => $name . ' site',
            'lat' => $coords[0],
            'lng' => $coords[1],
            'geo_fence_radius' => $radius,
            'is_active' => true,
        ]);

        return $client;
    }

    private function staffOf(Client $client, string $email): User
    {
        Role::findOrCreate('employee', 'web');

        $user = User::create(['name' => 'Staff ' . $client->id, 'email' => $email, 'password' => bcrypt('secret')]);
        $user->assignRole('employee');

        $employee = Employee::create([
            'user_id' => $user->id,
            'emp_number' => 'EMP' . str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
            'first_name' => 'Test',
            'last_name' => 'Staff',
            'status' => 'active',
            'hire_date' => now()->subYear(),
        ]);

        // The posting is a pivot row, not a column on employees - which is also
        // how AttendanceController resolves whose fence to measure against.
        $client->employees()->attach($employee->id, ['assigned_by' => $user->id]);

        return $user;
    }

    private function clockIn(User $user, array $coords)
    {
        return $this->actingAs($user)->post('/attendance/clock-in', [
            'lat' => $coords[0],
            'lng' => $coords[1],
        ]);
    }

    // ===== The bug =====

    public function test_head_office_staff_clocking_in_at_a_client_are_verified(): void
    {
        $hq = $this->client('Mastermind Consult Ltd HQ', self::HQ, headOffice: true);
        $roofings = $this->client('Roofings Uganda Limited', self::LUBOWA);
        $staff = $this->staffOf($hq, 'hq@test.local');

        $this->clockIn($staff, self::LUBOWA);

        $log = AttendanceLog::first();

        $this->assertSame(
            AttendanceLog::LOCATION_VERIFIED,
            $log->location_status,
            'An HQ visitor standing at a client was still recorded as off-site.'
        );
        // Their employer is unchanged - payroll keys on this.
        $this->assertSame($hq->id, $log->client_id);
        // But the row says where they actually were.
        $this->assertSame($roofings->id, $log->verified_at_client_id);
    }

    public function test_head_office_staff_are_still_verified_at_head_office(): void
    {
        $hq = $this->client('Mastermind Consult Ltd HQ', self::HQ, headOffice: true);
        $this->client('Roofings Uganda Limited', self::LUBOWA);
        $staff = $this->staffOf($hq, 'hq2@test.local');

        $this->clockIn($staff, self::HQ);

        $log = AttendanceLog::first();

        $this->assertSame(AttendanceLog::LOCATION_VERIFIED, $log->location_status);
        // Their own posting, so nothing to say about where they were.
        $this->assertNull($log->verified_at_client_id);
    }

    public function test_head_office_staff_nowhere_near_anything_are_still_flagged(): void
    {
        $hq = $this->client('Mastermind Consult Ltd HQ', self::HQ, headOffice: true);
        $this->client('Roofings Uganda Limited', self::LUBOWA);
        $staff = $this->staffOf($hq, 'hq3@test.local');

        $this->clockIn($staff, self::NOWHERE);

        $log = AttendanceLog::first();

        $this->assertSame(
            AttendanceLog::LOCATION_OUTSIDE,
            $log->location_status,
            'Roaming must not mean anywhere on earth counts.'
        );
        $this->assertNull($log->verified_at_client_id);
    }

    public function test_the_nearest_premises_wins_not_the_first(): void
    {
        $hq = $this->client('Mastermind Consult Ltd HQ', self::HQ, headOffice: true);
        $this->client('Far Client', self::LUBOWA);
        $near = $this->client('Near Client', self::INDUSTRIAL_AREA);
        $staff = $this->staffOf($hq, 'hq4@test.local');

        $this->clockIn($staff, self::INDUSTRIAL_AREA);

        $this->assertSame($near->id, AttendanceLog::first()->verified_at_client_id);
    }

    // ===== Everyone else is untouched =====

    public function test_posted_staff_at_another_clients_site_are_still_flagged(): void
    {
        $roofings = $this->client('Roofings Uganda Limited', self::LUBOWA);
        $this->client('Someone Else', self::INDUSTRIAL_AREA);
        $staff = $this->staffOf($roofings, 'posted@test.local');

        // Standing at a different client's premises. For a posted employee that
        // is exactly the exception the fence exists to catch.
        $this->clockIn($staff, self::INDUSTRIAL_AREA);

        $log = AttendanceLog::first();

        $this->assertSame(
            AttendanceLog::LOCATION_OUTSIDE,
            $log->location_status,
            'Roaming leaked to staff who are posted to one site.'
        );
        $this->assertNull($log->verified_at_client_id);
    }

    public function test_posted_staff_at_their_own_site_are_verified(): void
    {
        $roofings = $this->client('Roofings Uganda Limited', self::LUBOWA);
        $staff = $this->staffOf($roofings, 'posted2@test.local');

        $this->clockIn($staff, self::LUBOWA);

        $this->assertSame(AttendanceLog::LOCATION_VERIFIED, AttendanceLog::first()->location_status);
    }

    /** The fence records, it never refuses - that must survive this change. */
    public function test_an_off_site_clock_in_is_still_recorded(): void
    {
        $hq = $this->client('Mastermind Consult Ltd HQ', self::HQ, headOffice: true);
        $staff = $this->staffOf($hq, 'hq5@test.local');

        $this->clockIn($staff, self::NOWHERE);

        $log = AttendanceLog::first();
        $this->assertNotNull($log->clock_in, 'The clock-in was refused instead of flagged.');
        $this->assertSame('present', $log->status);
    }

    public function test_a_visitor_is_told_where_they_were_recognised(): void
    {
        $hq = $this->client('Mastermind Consult Ltd HQ', self::HQ, headOffice: true);
        $this->client('Roofings Uganda Limited', self::LUBOWA);
        $staff = $this->staffOf($hq, 'hq6@test.local');

        $this->clockIn($staff, self::LUBOWA)
            ->assertSessionHas('warning', fn ($m) => str_contains($m, 'Roofings Uganda Limited'));
    }
}
