<?php
namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $date  = $request->get("date", Carbon::today()->toDateString());
        $query = AttendanceLog::with(["employee.department"])->whereDate("date", $date);
        if ($request->filled("department_id")) {
            $query->whereHas("employee", fn($q) => $q->where("department_id", $request->department_id));
        }
        if ($request->filled("status")) $query->where("status", $request->status);

        if ($request->ajax()) return response()->json($query->get());

        $logs        = $query->paginate(25);
        $departments = \App\Models\Department::where("is_active",true)->get();
        $summary     = [
            "present" => AttendanceLog::whereDate("date", $date)->where("status","present")->count(),
            "absent"  => AttendanceLog::whereDate("date", $date)->where("status","absent")->count(),
            "late"    => AttendanceLog::whereDate("date", $date)->where("status","late")->count(),
            "total"   => Employee::where("status","active")->count(),
        ];
        return view("attendance.index", compact("logs","date","departments","summary"));
    }

    public function clockIn(Request $request)
    {
        $employee = Employee::where("user_id", auth()->id())->firstOrFail();
        $today    = Carbon::today();
        $existing = AttendanceLog::where("employee_id", $employee->id)->whereDate("date", $today)->first();
        if ($existing && $existing->clock_in) return response()->json(["message" => "Already clocked in today."], 422);

        $log = AttendanceLog::updateOrCreate(
            ["employee_id" => $employee->id, "date" => $today],
            ["clock_in" => now(), "status" => "present", "lat" => $request->lat, "lng" => $request->lng]
        );
        return response()->json(["success" => true, "time" => now()->format("H:i"), "message" => "Clocked in at " . now()->format("H:i")]);
    }

    public function clockOut(Request $request)
    {
        $employee = Employee::where("user_id", auth()->id())->firstOrFail();
        $log      = AttendanceLog::where("employee_id", $employee->id)->whereDate("date", Carbon::today())->firstOrFail();
        if ($log->clock_out) return response()->json(["message" => "Already clocked out."], 422);

        $hours   = Carbon::parse($log->clock_in)->diffInHours(now());
        $overtime = max(0, $hours - 8);
        $log->update(["clock_out" => now(), "overtime_hours" => $overtime]);
        return response()->json(["success" => true, "time" => now()->format("H:i"), "message" => "Clocked out at " . now()->format("H:i")]);
    }

    public function create()  { return view("attendance.create"); }
    public function store(Request $request) {
        $data = $request->validate(["employee_id" => "required|exists:employees,id", "date" => "required|date", "clock_in" => "nullable", "clock_out" => "nullable", "status" => "required|in:present,absent,late,half_day,holiday,weekend", "note" => "nullable|string"]);
        AttendanceLog::updateOrCreate(["employee_id" => $data["employee_id"], "date" => $data["date"]], $data);
        if ($request->ajax()) return response()->json(["success" => true]);
        return back()->with("success", "Attendance recorded.");
    }

    public function edit(AttendanceLog $attendance) { return view("attendance.edit", compact("attendance")); }
    public function update(Request $request, AttendanceLog $attendance) {
        $data = $request->validate(["clock_in" => "nullable", "clock_out" => "nullable", "status" => "required", "note" => "nullable"]);
        $attendance->update($data);
        return back()->with("success", "Attendance updated.");
    }
    public function destroy(AttendanceLog $attendance) { $attendance->delete(); return back()->with("success", "Record deleted."); }
    public function show(AttendanceLog $attendance)    { return view("attendance.show", compact("attendance")); }

    public function report(Request $request)
    {
        $month = $request->get("month", Carbon::today()->format("Y-m"));
        $employees = Employee::with(["attendanceLogs" => function ($q) use ($month) {
            $q->where("date", "like", "$month%");
        }])->where("status","active")->orderBy("first_name")->get();
        return view("attendance.report", compact("employees","month"));
    }

    public function shifts()
    {
        $shifts = Shift::orderBy("name")->get();
        return view("attendance.shifts", compact("shifts"));
    }
    public function storeShift(Request $request) {
        $data = $request->validate(["name" => "required|string|max:100|unique:shifts", "start_time" => "required", "end_time" => "required", "grace_minutes" => "integer|min:0"]);
        Shift::create($data);
        return back()->with("success", "Shift created.");
    }
    public function updateShift(Request $request, Shift $shift) {
        $shift->update($request->validate(["name" => "required|string|max:100", "start_time" => "required", "end_time" => "required", "grace_minutes" => "integer|min:0", "is_active" => "boolean"]));
        return back()->with("success", "Shift updated.");
    }
    public function destroyShift(Shift $shift) { $shift->delete(); return back()->with("success", "Shift deleted."); }

    public function holidays()
    {
        $holidays = Holiday::orderBy("date")->get();
        return view("attendance.holidays", compact("holidays"));
    }
    public function storeHoliday(Request $request) {
        $data = $request->validate(["name" => "required|string", "date" => "required|date", "is_recurring" => "boolean", "description" => "nullable|string"]);
        Holiday::create($data);
        return back()->with("success", "Holiday added.");
    }
    public function destroyHoliday(Holiday $holiday) { $holiday->delete(); return back()->with("success", "Holiday removed."); }
}
