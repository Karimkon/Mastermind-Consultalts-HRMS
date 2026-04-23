<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{AttendanceLog, Employee, Setting};
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
        if ($request->date_from)   $query->whereDate('clock_in', '>=', $request->date_from);
        if ($request->date_to)     $query->whereDate('clock_in', '<=', $request->date_to);

        return response()->json([
            'data' => $query->latest('clock_in')->paginate(20)->through(fn($a) => $this->format($a)),
        ]);
    }

    public function today(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) return response()->json(['data' => null]);

        $log = AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('clock_in', Carbon::today())->latest()->first();

        return response()->json(['data' => $log ? $this->format($log) : null]);
    }

    public function clockIn(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) return response()->json(['message' => 'No employee profile.'], 422);

        $existing = AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('clock_in', Carbon::today())
            ->whereNull('clock_out')->first();

        if ($existing) return response()->json(['message' => 'Already clocked in.'], 422);

        $geoError = $this->geoFenceCheck($request->latitude, $request->longitude);
        if ($geoError) return response()->json(['message' => $geoError, 'geo_error' => true], 422);

        $log = AttendanceLog::create([
            'employee_id' => $employee->id,
            'clock_in'    => now(),
            'date'        => Carbon::today()->format('Y-m-d'),
            'status'      => 'present',
        ]);

        return response()->json(['data' => $this->format($log), 'message' => 'Clocked in successfully.'], 201);
    }

    public function clockOut(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) return response()->json(['message' => 'No employee profile.'], 422);

        $log = AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('clock_in', Carbon::today())
            ->whereNull('clock_out')->latest()->first();

        if (!$log) return response()->json(['message' => 'No active clock-in found.'], 422);

        $geoError = $this->geoFenceCheck($request->latitude, $request->longitude);
        if ($geoError) return response()->json(['message' => $geoError, 'geo_error' => true], 422);

        $log->update(['clock_out' => now()]);

        return response()->json(['data' => $this->format($log->fresh()), 'message' => 'Clocked out successfully.']);
    }

    public function report(Request $request)
    {
        $user = $request->user();
        $query = AttendanceLog::with('employee.user');

        if ($user->hasRole('employee') && !$user->hasRole(['super-admin','hr-admin','manager'])) {
            $query->where('employee_id', $user->employee?->id);
        }

        if ($request->employee_id) $query->where('employee_id', $request->employee_id);
        if ($request->month)       $query->whereMonth('clock_in', $request->month);
        if ($request->year)        $query->whereYear('clock_in', $request->year ?? now()->year);

        return response()->json([
            'data' => $query->latest('clock_in')->get()->map(fn($a) => $this->format($a)),
        ]);
    }

    private function geoFenceCheck(?float $lat, ?float $lng): ?string
    {
        $officeLat = Setting::get('office_lat');
        $officeLng = Setting::get('office_lng');
        $radius    = Setting::get('geo_radius_meters', 100);
        if (!$officeLat || !$officeLng || !$lat || !$lng) return null;
        $R    = 6371000;
        $phi1 = deg2rad((float)$officeLat);
        $phi2 = deg2rad($lat);
        $dphi = deg2rad($lat - (float)$officeLat);
        $dlam = deg2rad($lng - (float)$officeLng);
        $a    = sin($dphi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dlam / 2) ** 2;
        $dist = 2 * $R * asin(sqrt($a));
        return $dist > (float)$radius
            ? "You are outside the office geo-fence ({$dist}m from the office, max {$radius}m)."
            : null;
    }

    private function format(AttendanceLog $a): array
    {
        $clockIn  = $a->clock_in  ? Carbon::parse($a->clock_in)  : null;
        $clockOut = $a->clock_out ? Carbon::parse($a->clock_out) : null;

        return [
            'id'            => $a->id,
            'employee_id'   => $a->employee_id,
            'employee_name' => $a->employee?->full_name,
            'avatar_url'    => $a->employee?->user?->avatar_url,
            'date'          => $clockIn?->format('Y-m-d'),
            'clock_in'      => $clockIn?->format('H:i'),
            'clock_out'     => $clockOut?->format('H:i'),
            'hours_worked'  => $clockIn && $clockOut ? round($clockIn->diffInMinutes($clockOut) / 60, 1) : null,
            'status'        => $a->status ?? 'present',
            'is_clocked_in' => $clockIn && !$clockOut,
        ];
    }
}
