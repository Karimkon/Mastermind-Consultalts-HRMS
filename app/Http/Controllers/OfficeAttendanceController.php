<?php
namespace App\Http\Controllers;

use App\Models\{OfficeAttendance, Setting, User};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Daily office presence register for head-office staff and account managers.
 *
 * Separate from AttendanceController on purpose: nothing here feeds payroll.
 * A missing clock-in is shown as a gap in the register and costs nobody a
 * shilling — that is exactly what was asked for.
 */
class OfficeAttendanceController extends Controller
{
    /** Roles expected to clock in at the office each day. */
    private const OFFICE_ROLES = [
        'account-manager', 'hr-admin', 'manager', 'payroll-officer',
        'recruiter', 'super-admin', 'md',
    ];

    /** Roles allowed to see everyone's register rather than just their own. */
    private const SUPERVISOR_ROLES = ['super-admin', 'hr-admin', 'manager', 'md'];

    private function distanceMetres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371000;
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dphi = deg2rad($lat2 - $lat1);
        $dlam = deg2rad($lng2 - $lng1);
        $a    = sin($dphi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dlam / 2) ** 2;
        return 2 * $R * asin(sqrt($a));
    }

    /** [lat, lng, radius] of the head office, or null when not configured yet. */
    private function officeLocation(): ?array
    {
        $lat = Setting::get('office_lat');
        $lng = Setting::get('office_lng');
        if (!$lat || !$lng) return null;

        return [(float) $lat, (float) $lng, (float) (Setting::get('geo_radius_meters') ?: 150)];
    }

    /**
     * How far the tap was from the office and whether that counts as off-site.
     * Returns [distance|null, offsite]. With no office set, or no GPS from the
     * browser, nothing is flagged — an unconfigured office must not paint every
     * record red.
     */
    private function locate(?float $lat, ?float $lng): array
    {
        $office = $this->officeLocation();
        if (!$office || $lat === null || $lng === null) {
            return [null, false];
        }

        [$oLat, $oLng, $radius] = $office;
        $distance = (int) round($this->distanceMetres($lat, $lng, $oLat, $oLng));

        return [$distance, $distance > $radius];
    }

    public function index(Request $request)
    {
        $user       = auth()->user();
        $isSuper    = $user->hasAnyRole(self::SUPERVISOR_ROLES);
        $date       = $request->date ? Carbon::parse($request->date)->toDateString() : today()->toDateString();

        // Everyone who is expected at the office, so the register can show the
        // people who did not clock in as well as the people who did.
        $expected = User::role(self::OFFICE_ROLES)->orderBy('name')->get();

        $logs = OfficeAttendance::with('user')
            ->whereDate('date', $date)
            ->when(!$isSuper, fn($q) => $q->where('user_id', $user->id))
            ->get()->keyBy('user_id');

        $roster = ($isSuper ? $expected : $expected->where('id', $user->id))
            ->map(fn($u) => ['user' => $u, 'log' => $logs->get($u->id)])
            ->values();

        $summary = [
            'expected'   => $roster->count(),
            'clocked_in' => $roster->filter(fn($r) => $r['log']?->clock_in)->count(),
            'still_in'   => $roster->filter(fn($r) => $r['log']?->is_open)->count(),
            'missing'    => $roster->filter(fn($r) => !$r['log']?->clock_in)->count(),
            'offsite'    => $roster->filter(fn($r) => $r['log']?->clock_in_offsite)->count(),
        ];

        $myLog = OfficeAttendance::where('user_id', $user->id)->whereDate('date', today())->first();

        return view('office-attendance.index', [
            'roster'       => $roster,
            'summary'      => $summary,
            'myLog'        => $myLog,
            'date'         => $date,
            'isSupervisor' => $isSuper,
            'office'       => $this->officeLocation(),
        ]);
    }

    public function clockIn(Request $request)
    {
        $user = auth()->user();
        $log  = OfficeAttendance::firstOrCreate(['user_id' => $user->id, 'date' => today()]);

        if ($log->clock_in) {
            return back()->with('error', 'You have already clocked in at the office today.');
        }

        $lat = $request->filled('lat') ? (float) $request->lat : null;
        $lng = $request->filled('lng') ? (float) $request->lng : null;
        [$distance, $offsite] = $this->locate($lat, $lng);

        $log->update([
            'clock_in'            => now(),
            'clock_in_lat'        => $lat,
            'clock_in_lng'        => $lng,
            'clock_in_distance_m' => $distance,
            'clock_in_offsite'    => $offsite,
        ]);

        // Recorded either way — the flag is information, not a barrier.
        $msg = 'Clocked in at the office at ' . now()->format('H:i') . '.';
        if ($offsite) {
            $msg .= " Recorded as off-site — you were about {$distance}m from the office.";
        }

        return back()->with($offsite ? 'warning' : 'success', $msg);
    }

    public function clockOut(Request $request)
    {
        $user = auth()->user();
        $log  = OfficeAttendance::where('user_id', $user->id)->whereDate('date', today())->first();

        if (!$log || !$log->clock_in) {
            return back()->with('error', 'You have not clocked in at the office today.');
        }
        if ($log->clock_out) {
            return back()->with('error', 'You have already clocked out today.');
        }

        $lat = $request->filled('lat') ? (float) $request->lat : null;
        $lng = $request->filled('lng') ? (float) $request->lng : null;
        [$distance, $offsite] = $this->locate($lat, $lng);

        $log->update([
            'clock_out'            => now(),
            'clock_out_lat'        => $lat,
            'clock_out_lng'        => $lng,
            'clock_out_distance_m' => $distance,
            'clock_out_offsite'    => $offsite,
        ]);

        return back()->with('success',
            'Clocked out at ' . now()->format('H:i') . '. Time at office: ' . $log->fresh()->hours . 'h.');
    }
}
