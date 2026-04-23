<?php
namespace App\Http\Controllers;

use App\Models\{AttendanceLog, Employee, Department, Shift, Holiday, Setting};
use Illuminate\Http\Request;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    private function distanceMetres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000;
        $phi1 = deg2rad($lat1); $phi2 = deg2rad($lat2);
        $dphi = deg2rad($lat2 - $lat1);
        $dlam = deg2rad($lng2 - $lng1);
        $a = sin($dphi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dlam / 2) ** 2;
        return 2 * $R * asin(sqrt($a));
    }

    private function geoFenceCheck($lat, $lng): ?string
    {
        $officeLat = Setting::where("key", "office_lat")->value("value");
        $officeLng = Setting::where("key", "office_lng")->value("value");
        $radius    = (float)(Setting::where("key", "geo_radius_meters")->value("value") ?? 100);
        if (!$officeLat || !$officeLng) return null;
        if ($lat === null || $lng === null) {
            return "Your location is required to clock in. Please allow location access.";
        }
        $distance = $this->distanceMetres((float)$lat, (float)$lng, (float)$officeLat, (float)$officeLng);
        if ($distance > $radius) {
            return "You are " . round($distance) . "m from the office. Must be within " . (int)$radius . "m to clock in/out.";
        }
        return null;
    }

    public function index(Request $request)
    {
        $user     = auth()->user();
        $employee = $user->employee;
        $isAdmin  = $user->hasAnyRole(['super-admin','hr-admin','manager']);

        $query = AttendanceLog::with(['employee.department'])
            ->when(!$isAdmin && $employee, fn($q) => $q->where('employee_id', $employee->id))
            ->when($isAdmin && $request->employee_id, fn($q) => $q->where('employee_id', $request->employee_id))
            ->when($isAdmin && $request->department_id, fn($q) => $q->whereHas('employee', fn($e) => $e->where('department_id', $request->department_id)))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->date, fn($q) => $q->whereDate('date', $request->date))
            ->when($request->date_from, fn($q) => $q->whereDate('date', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->whereDate('date', '<=', $request->date_to))
            ->orderByDesc('date');

        $logs        = $query->paginate(30);
        $departments = Department::orderBy('name')->get();
        $present  = (clone $query)->where('status','present')->count();
        $absent   = (clone $query)->where('status','absent')->count();
        $late     = (clone $query)->where('status','late')->count();
        $summary  = ['present' => $present, 'absent' => $absent, 'late' => $late, 'total' => $present + $absent + $late];
        $myLog    = auth()->user()->employee ? AttendanceLog::where('employee_id', auth()->user()->employee->id)->whereDate('date', today())->first() : null;
        $date     = $request->date ?? '';

        return view('attendance.index', compact('logs', 'departments', 'summary', 'myLog', 'date'));
    }

    public function clockIn(Request $request)
    {
        $employee = auth()->user()->employee;
        if (!$employee) {
            return $request->wantsJson()
                ? response()->json(['error' => 'No employee profile.'], 403)
                : back()->with('error', 'No employee profile.');
        }

        $log = AttendanceLog::firstOrCreate(['employee_id' => $employee->id, 'date' => today()]);
        if ($log->clock_in) {
            return $request->wantsJson()
                ? response()->json(['error' => 'Already clocked in.'], 422)
                : back()->with('error', 'You have already clocked in today.');
        }

        $log->update(['clock_in' => now(), 'status' => 'present', 'lat' => $request->lat, 'lng' => $request->lng]);

        return $request->wantsJson()
            ? response()->json(['time' => now()->format('H:i'), 'message' => 'Clocked in successfully.'])
            : back()->with('success', 'Clocked in at ' . now()->format('H:i') . '.');
    }

    public function clockOut(Request $request)
    {
        $employee = auth()->user()->employee;
        $log = AttendanceLog::where('employee_id', $employee->id)->whereDate('date', today())->first();

        if (!$log || !$log->clock_in) {
            return $request->wantsJson()
                ? response()->json(['error' => 'Not clocked in.'], 422)
                : back()->with('error', 'You have not clocked in today.');
        }

        $geoError = $this->geoFenceCheck($request->lat !== null ? (float)$request->lat : null, $request->lng !== null ? (float)$request->lng : null);
        if ($geoError) {
            return $request->wantsJson() ? response()->json(["error" => $geoError], 422) : back()->with("error", $geoError);
        }
        $hours    = Carbon::parse($log->clock_in)->diffInMinutes(now()) / 60;
        $overtime = max(0, $hours - 8);
        $log->update(['clock_out' => now(), 'overtime_hours' => round($overtime, 2)]);

        return $request->wantsJson()
            ? response()->json(['time' => now()->format('H:i'), 'hours' => round($hours, 1)])
            : back()->with('success', 'Clocked out at ' . now()->format('H:i') . '. Hours worked: ' . round($hours, 1) . 'h.');
    }

    public function create() { return view('attendance.create', ['employees' => Employee::with('user')->where('status','active')->get()]); }

    public function store(Request $request)
    {
        $request->validate(['employee_id'=>'required','date'=>'required|date','status'=>'required']);
        AttendanceLog::updateOrCreate(['employee_id'=>$request->employee_id,'date'=>$request->date], $request->only('clock_in','clock_out','status','overtime_hours'));
        return redirect()->route('attendance.index')->with('success', 'Attendance recorded.');
    }

    public function show(AttendanceLog $attendance) { return view('attendance.show', ['attendance' => $attendance->load('employee')]); }

    public function edit(AttendanceLog $attendance)
    {
        $employees = Employee::with('user')->where('status','active')->get();
        return view('attendance.edit', compact('attendance', 'employees'));
    }

    public function update(Request $request, AttendanceLog $attendance)
    {
        $attendance->update($request->only('clock_in','clock_out','status','overtime_hours'));
        return redirect()->route('attendance.index')->with('success', 'Attendance updated.');
    }

    public function destroy(AttendanceLog $attendance) { $attendance->delete(); return back()->with('success', 'Deleted.'); }

    public function report(Request $request)
    {
        $departments = Department::orderBy('name')->get();
        return view('attendance.report', compact('departments'));
    }

    public function shifts()
    {
        $shifts = Shift::paginate(20);
        return view('attendance.shifts', compact('shifts'));
    }

    public function storeShift(Request $request)
    {
        $request->validate(['name'=>'required','start_time'=>'required','end_time'=>'required']);
        Shift::create($request->only('name','start_time','end_time','grace_minutes'));
        return back()->with('success', 'Shift created.');
    }

    public function holidays()
    {
        $holidays = Holiday::orderBy('date')->paginate(20);
        return view('attendance.holidays', compact('holidays'));
    }

    public function storeHoliday(Request $request)
    {
        $request->validate(['name'=>'required','date'=>'required|date']);
        Holiday::create($request->only('name','date','type'));
        return back()->with('success', 'Holiday added.');
    }
}