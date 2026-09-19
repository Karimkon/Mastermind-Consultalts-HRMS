<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{OfficeAttendance, Setting, User};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Mobile endpoints for the office presence register.
 *
 * Keyed on the signed-in user, not an employee record, so account managers can
 * use it. Nothing here feeds payroll — see the office_attendance migration.
 */
class OfficeAttendanceApiController extends Controller
{
    private const OFFICE_ROLES = [
        'account-manager', 'hr-admin', 'manager', 'payroll-officer',
        'recruiter', 'super-admin', 'md',
    ];

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

    private function officeLocation(): ?array
    {
        $lat = Setting::get('office_lat');
        $lng = Setting::get('office_lng');
        if (!$lat || !$lng) return null;

        return [(float) $lat, (float) $lng, (float) (Setting::get('geo_radius_meters') ?: 150)];
    }

    /** [distance|null, offsite] — nothing is flagged when the office is unset. */
    private function locate(?float $lat, ?float $lng): array
    {
        $office = $this->officeLocation();
        if (!$office || $lat === null || $lng === null) return [null, false];

        [$oLat, $oLng, $radius] = $office;
        $distance = (int) round($this->distanceMetres($lat, $lng, $oLat, $oLng));

        return [$distance, $distance > $radius];
    }

    private function format(?OfficeAttendance $log): ?array
    {
        if (!$log) return null;

        return [
            'id'                   => $log->id,
            'user_id'              => $log->user_id,
            'user_name'            => $log->user?->name,
            'date'                 => $log->date?->toDateString(),
            'clock_in'             => $log->clock_in?->toIso8601String(),
            'clock_out'            => $log->clock_out?->toIso8601String(),
            'clock_in_time'        => $log->clock_in?->format('H:i'),
            'clock_out_time'       => $log->clock_out?->format('H:i'),
            'hours'                => $log->hours,
            'clock_in_distance_m'  => $log->clock_in_distance_m,
            'clock_out_distance_m' => $log->clock_out_distance_m,
            'clock_in_offsite'     => (bool) $log->clock_in_offsite,
            'clock_out_offsite'    => (bool) $log->clock_out_offsite,
            'is_open'              => $log->is_open,
            'status_label'         => $log->status_label,
        ];
    }

    /** Today's own record plus the office config the app needs to show distance. */
    public function today(Request $request)
    {
        $user   = $request->user();
        $log    = OfficeAttendance::with('user')
            ->where('user_id', $user->id)->whereDate('date', Carbon::today())->first();
        $office = $this->officeLocation();

        return response()->json([
            'data'   => $this->format($log),
            'office' => $office ? [
                'lat'             => $office[0],
                'lng'             => $office[1],
                'radius'          => $office[2],
                'has_coordinates' => true,
            ] : ['has_coordinates' => false],
        ]);
    }

    /** Daily register — everyone expected, including those who never clocked in. */
    public function index(Request $request)
    {
        $user  = $request->user();
        $date  = $request->date ? Carbon::parse($request->date)->toDateString() : Carbon::today()->toDateString();
        $isSup = $user->hasAnyRole(self::SUPERVISOR_ROLES);

        $expected = User::role(self::OFFICE_ROLES)->orderBy('name')->get();
        $logs     = OfficeAttendance::with('user')->whereDate('date', $date)
            ->when(!$isSup, fn($q) => $q->where('user_id', $user->id))
            ->get()->keyBy('user_id');

        $roster = ($isSup ? $expected : $expected->where('id', $user->id))
            ->map(fn($u) => [
                'user_id'   => $u->id,
                'user_name' => $u->name,
                'roles'     => $u->getRoleNames(),
                'log'       => $this->format($logs->get($u->id)),
            ])->values();

        return response()->json([
            'date'    => $date,
            'data'    => $roster,
            'summary' => [
                'expected'   => $roster->count(),
                'clocked_in' => $roster->filter(fn($r) => $r['log'] && $r['log']['clock_in'])->count(),
                'still_in'   => $roster->filter(fn($r) => $r['log'] && $r['log']['is_open'])->count(),
                'missing'    => $roster->filter(fn($r) => !$r['log'] || !$r['log']['clock_in'])->count(),
                'offsite'    => $roster->filter(fn($r) => $r['log'] && $r['log']['clock_in_offsite'])->count(),
            ],
            'is_supervisor' => $isSup,
        ]);
    }

    public function clockIn(Request $request)
    {
        $request->validate([
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        $user = $request->user();
        $log  = OfficeAttendance::firstOrCreate(['user_id' => $user->id, 'date' => Carbon::today()]);

        if ($log->clock_in) {
            return response()->json(['message' => 'You have already clocked in at the office today.'], 422);
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

        // Recorded regardless of location — the flag is information, not a barrier.
        return response()->json([
            'message' => $offsite
                ? "Clocked in at " . now()->format('H:i') . ". Recorded as off-site — about {$distance}m from the office."
                : 'Clocked in at the office at ' . now()->format('H:i') . '.',
            'offsite' => $offsite,
            'data'    => $this->format($log->fresh()->load('user')),
        ]);
    }

    public function clockOut(Request $request)
    {
        $request->validate([
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        $user = $request->user();
        $log  = OfficeAttendance::where('user_id', $user->id)->whereDate('date', Carbon::today())->first();

        if (!$log || !$log->clock_in) {
            return response()->json(['message' => 'You have not clocked in at the office today.'], 422);
        }
        if ($log->clock_out) {
            return response()->json(['message' => 'You have already clocked out today.'], 422);
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

        $fresh = $log->fresh()->load('user');

        return response()->json([
            'message' => 'Clocked out at ' . now()->format('H:i') . '. Time at office: ' . $fresh->hours . 'h.',
            'data'    => $this->format($fresh),
        ]);
    }
}
