<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{AttendanceLog, Client, Employee, Setting};
use Illuminate\Http\Request;
use Carbon\Carbon;

class AttendanceApiController extends Controller
{
    public function index(Request $request)
    {
        $user  = $request->user();
        $query = AttendanceLog::with(['employee.user']);

        if ($user->hasRole('employee') && !$user->hasRole(['super-admin','hr-admin','manager'])) {
            $query->where('employee_id', $user->employee?->id);
        }

        if ($request->employee_id) $query->where('employee_id', $request->employee_id);
        if ($request->date_from)   $query->whereDate('date', '>=', $request->date_from);
        if ($request->date_to)     $query->whereDate('date', '<=', $request->date_to);
        if ($request->month)       $query->whereMonth('date', $request->month);
        if ($request->year)        $query->whereYear('date', $request->year);
        if ($request->status && $request->status !== 'all') $query->where('status', $request->status);

        return response()->json([
            'data' => $query->latest('date')->paginate(20)->through(fn($a) => $this->format($a)),
        ]);
    }

    public function today(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) return response()->json(['data' => null, 'work_site' => null]);

        $log    = AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('date', Carbon::today())->latest()->first();
        $client = $this->getEmployeeClient($employee);

        return response()->json([
            'data'      => $log ? $this->format($log) : null,
            'work_site' => $client ? [
                'company_name'      => $client->company_name,
                'work_site_address' => $client->work_site_address,
                'geo_fence_radius'  => $client->geo_fence_radius ?? 100,
                'has_coordinates'   => !empty($client->work_site_lat) && !empty($client->work_site_lng),
                'work_site_lat'     => $client->work_site_lat,
                'work_site_lng'     => $client->work_site_lng,
            ] : null,
        ]);
    }

    public function clockIn(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) return response()->json(['message' => 'No employee profile.'], 422);

        $existing = AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('date', Carbon::today())
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')->first();

        if ($existing) return response()->json(['message' => 'Already clocked in.'], 422);

        // Per-client geo-fence enforcement
        $client   = $this->getEmployeeClient($employee);
        $geoError = $this->employeeGeoCheck($client, $request->latitude, $request->longitude);
        if ($geoError) return response()->json(['message' => $geoError, 'geo_error' => true], 422);

        $distance = $client ? $this->distanceMetres(
            (float)$request->latitude, (float)$request->longitude,
            (float)$client->work_site_lat, (float)$client->work_site_lng
        ) : null;

        $log = AttendanceLog::create([
            'employee_id'       => $employee->id,
            'client_id'         => $client?->id,
            'clock_in'          => now(),
            'date'              => Carbon::today()->format('Y-m-d'),
            'lat'               => $request->latitude,
            'lng'               => $request->longitude,
            'distance_metres'   => $distance ? round($distance, 2) : null,
            'status'            => 'present',
        ]);

        return response()->json(['data' => $this->format($log), 'message' => 'Clocked in successfully.'], 201);
    }

    public function clockOut(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) return response()->json(['message' => 'No employee profile.'], 422);

        $log = AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('date', Carbon::today())
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')->latest()->first();

        if (!$log) return response()->json(['message' => 'No active clock-in found.'], 422);

        // Per-client geo-fence enforcement
        $client   = $this->getEmployeeClient($employee);
        $geoError = $this->employeeGeoCheck($client, $request->latitude, $request->longitude);
        if ($geoError) return response()->json(['message' => $geoError, 'geo_error' => true], 422);

        $clockIn  = Carbon::parse($log->clock_in);
        $hours    = $clockIn->diffInMinutes(now()) / 60;
        $overtime = max(0, $hours - 8);

        $distance = ($client && $request->latitude && $request->longitude) ? $this->distanceMetres(
            (float)$request->latitude, (float)$request->longitude,
            (float)$client->work_site_lat, (float)$client->work_site_lng
        ) : null;

        $log->update([
            'clock_out'         => now(),
            'clock_out_lat'     => $request->latitude,
            'clock_out_lng'     => $request->longitude,
            'overtime_hours'    => round($overtime, 2),
        ]);

        return response()->json([
            'data'    => $this->format($log->fresh()),
            'message' => 'Clocked out successfully.',
            'hours'   => round($hours, 1),
        ]);
    }

    public function report(Request $request)
    {
        $user = $request->user();
        $query = AttendanceLog::with('employee.user');

        if ($user->hasRole('employee') && !$user->hasRole(['super-admin','hr-admin','manager'])) {
            $query->where('employee_id', $user->employee?->id);
        }

        if ($request->employee_id) $query->where('employee_id', $request->employee_id);
        if ($request->month)       $query->whereMonth('date', $request->month);
        if ($request->year)        $query->whereYear('date', $request->year ?? now()->year);

        return response()->json([
            'data' => $query->latest('date')->get()->map(fn($a) => $this->format($a)),
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    private function getEmployeeClient(Employee $employee): ?Client
    {
        return Client::whereHas('employees', fn($q) => $q->where('employees.id', $employee->id))->first();
    }

    private function employeeGeoCheck(?Client $client, $lat, $lng): ?string
    {
        if (!$client || !$client->work_site_lat || !$client->work_site_lng) return null;

        if ($lat === null || $lng === null) {
            return 'Your location is required to clock in/out. Please enable GPS.';
        }

        $distance = $this->distanceMetres((float)$lat, (float)$lng, (float)$client->work_site_lat, (float)$client->work_site_lng);
        $radius   = (int)($client->geo_fence_radius ?? 100);

        if ($distance > $radius) {
            return "You are " . round($distance) . "m from the work site ({$client->company_name}). Must be within {$radius}m.";
        }
        return null;
    }

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

    private function format(AttendanceLog $a): array
    {
        $clockIn  = $a->clock_in  ? Carbon::parse($a->clock_in)  : null;
        $clockOut = $a->clock_out ? Carbon::parse($a->clock_out) : null;

        return [
            'id'              => $a->id,
            'employee_id'     => $a->employee_id,
            'employee_name'   => $a->employee?->full_name,
            'avatar_url'      => $a->employee?->user?->avatar_url,
            'client_id'       => $a->client_id,
            'date'            => $clockIn?->format('Y-m-d') ?? $a->date?->format('Y-m-d'),
            'clock_in'        => $clockIn?->format('H:i'),
            'clock_out'       => $clockOut?->format('H:i'),
            'clock_in_lat'    => $a->lat,
            'clock_in_lng'    => $a->lng,
            'clock_out_lat'   => $a->clock_out_lat,
            'clock_out_lng'   => $a->clock_out_lng,
            'distance_metres' => $a->distance_metres,
            'hours_worked'    => $clockIn && $clockOut ? round($clockIn->diffInMinutes($clockOut) / 60, 1) : null,
            'overtime_hours'  => $a->overtime_hours,
            'status'          => $a->status ?? 'present',
            'is_clocked_in'   => $clockIn && !$clockOut,
        ];
    }
}
