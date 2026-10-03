<?php
namespace App\Http\Controllers;

use App\Models\{AttendanceLog, Employee, Client, Department, Shift, Holiday, Setting};
use Illuminate\Http\Request;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    use \App\Http\Controllers\Concerns\ChecksWorkSiteLocation;

    // =========================================================
    // Haversine distance in metres
    // =========================================================
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

    // =========================================================
    // Legacy global geo-fence (kept for non-client employees)
    // =========================================================

    // =========================================================
    // Per-client geo-fence for employees assigned to a client
    // Returns an error string on failure, null on pass.
    // =========================================================

    // =========================================================
    // INDEX
    // =========================================================
    /**
     * Clients the signed-in user is allowed to see attendance for.
     * Null means "no restriction" (admins); a collection means restrict to those.
     */
    /**
     * Roles that see attendance across every client. Finance is included
     * because days worked drive the payroll figures they have to verify before
     * approving — they already see every payslip and bank detail.
     */
    private const VIEW_ALL_ROLES = ['super-admin', 'hr-admin', 'manager', 'payroll-officer'];

    private function visibleClientIds(): ?\Illuminate\Support\Collection
    {
        $user = auth()->user();

        if ($user->hasAnyRole(self::VIEW_ALL_ROLES)) {
            return null;
        }

        if ($user->hasRole('account-manager')) {
            return Client::where('account_manager_id', $user->id)->pluck('id');
        }

        return collect();   // everyone else is scoped to their own record instead
    }

    public function index(Request $request)
    {
        $user     = auth()->user();
        $employee = $user->employee;
        $isAdmin  = $user->hasAnyRole(self::VIEW_ALL_ROLES);
        $isAm     = $user->hasRole('account-manager') && !$isAdmin;

        // Account managers see every employee on the client sites they run, not
        // just their own record — and never another manager's clients. Anyone
        // else without an admin role is limited to their own attendance.
        $amClientIds = $isAm ? $this->visibleClientIds() : null;
        $canSeeAll   = $isAdmin || $isAm;

        $query = AttendanceLog::with(['employee.department', 'client'])
            ->when($isAm, fn($q) => $q->whereHas(
                'employee.clients',
                fn($c) => $c->whereIn('clients.id', $amClientIds ?: [0])
            ))
            ->when(!$canSeeAll, fn($q) => $employee
                ? $q->where('employee_id', $employee->id)
                : $q->whereRaw('1 = 0'))     // no employee record → show nothing, never everything
            ->when($canSeeAll && $request->employee_id, fn($q) => $q->where('employee_id', $request->employee_id))
            ->when($canSeeAll && $request->client_id, fn($q) => $q->whereHas(
                'employee.clients',
                fn($c) => $c->where('clients.id', $request->client_id)
            ))
            ->when($canSeeAll && $request->department_id, fn($q) => $q->whereHas(
                'employee', fn($e) => $e->where('department_id', $request->department_id)
            ))
            ->when($canSeeAll && $request->section, fn($q) => $q->whereHas(
                'employee', fn($e) => $e->where('work_location', $request->section)
            ))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->date, fn($q) => $q->whereDate('date', $request->date))
            ->when($request->date_from, fn($q) => $q->whereDate('date', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->whereDate('date', '<=', $request->date_to))
            ->orderByDesc('date');

        $logs = $query->paginate(30)->withQueryString();

        // Counts must reflect the same filters as the table, so they are taken
        // from a clone of the filtered query rather than the whole table.
        $present = (clone $query)->where('status', 'present')->count();
        $absent  = (clone $query)->where('status', 'absent')->count();
        $late    = (clone $query)->where('status', 'late')->count();
        $summary = ['present' => $present, 'absent' => $absent, 'late' => $late, 'total' => $present + $absent + $late];

        // Filter options are scoped too, so a manager cannot infer another
        // client's structure from the dropdowns.
        $clients = Client::when($isAm, fn($q) => $q->whereIn('id', $amClientIds ?: [0]))
            ->orderBy('company_name')->get();

        $departments = Department::when($isAm, fn($q) => $q->whereHas(
                'employees.clients', fn($c) => $c->whereIn('clients.id', $amClientIds ?: [0])
            ))->orderBy('name')->get();

        $sections = Employee::query()
            ->when($isAm, fn($q) => $q->whereHas('clients', fn($c) => $c->whereIn('clients.id', $amClientIds ?: [0])))
            ->whereNotNull('work_location')->where('work_location', '<>', '')
            ->distinct()->orderBy('work_location')->pluck('work_location');

        $myLog = $employee ? AttendanceLog::where('employee_id', $employee->id)->whereDate('date', today())->first() : null;
        $date  = $request->date ?? '';

        // Geo-fence enabled if global setting exists OR if employee is assigned to a client with coords
        $geoEnabled = false;
        if ($employee) {
            $client = Client::whereHas('employees', fn($q) => $q->where('employees.id', $employee->id))->first();
            if ($client && $client->work_site_lat && $client->work_site_lng) {
                $geoEnabled = true;
            }
        }
        if (!$geoEnabled) {
            $geoEnabled = (bool) Setting::where('key', 'office_lat')->value('value')
                       && (bool) Setting::where('key', 'office_lng')->value('value');
        }

        return view('attendance.index', compact(
            'logs', 'departments', 'clients', 'sections',
            'summary', 'myLog', 'date', 'geoEnabled', 'canSeeAll'
        ));
    }

    // =========================================================
    // CLOCK IN
    // =========================================================
    public function clockIn(Request $request)
    {
        $employee = auth()->user()->employee;
        if (!$employee) {
            return $request->wantsJson()
                ? response()->json(['error' => 'No employee profile.'], 403)
                : back()->with('error', 'No employee profile.');
        }

        // A client may run its own register and switch this off entirely.
        $client = Client::whereHas('employees', fn($q) => $q->where('employees.id', $employee->id))->first();
        if ($client && !$client->attendance_enabled) {
            $msg = $client->company_name . ' does not use clock-in through this system.';
            return $request->wantsJson()
                ? response()->json(['error' => $msg], 422)
                : back()->with('error', $msg);
        }

        $log = AttendanceLog::firstOrCreate(['employee_id' => $employee->id, 'date' => today()]);
        if ($log->clock_in) {
            return $request->wantsJson()
                ? response()->json(['error' => 'Already clocked in.'], 422)
                : back()->with('error', 'You have already clocked in today.');
        }

        $lat = $request->lat !== null ? (float)$request->lat : null;
        $lng = $request->lng !== null ? (float)$request->lng : null;

        // The fence records rather than refuses. This used to reject anything
        // outside the radius, and attendance_logs feeds payroll, so a pin
        // dropped a couple of hundred metres off cost somebody the whole day
        // with no way to register that they had turned up. HR now sees the row
        // with its distance and decides.
        [$distance, $locationStatus, $site] = $this->assessLocation($client, $lat, $lng);
        $notice = $this->locationNotice($client, $distance, $locationStatus, $site);

        $log->update([
            'clock_in'        => now(),
            'status'          => 'present',
            'lat'             => $lat,
            'lng'             => $lng,
            'distance_metres' => $distance,
            'location_status' => $locationStatus,
            'client_id'       => $client?->id,      // resolved above for the enabled check
            // Which premises actually recognised the fix. Null when it was
            // their own posting; set when head office staff were at a client.
            'verified_at_client_id' => $this->verifiedAtClientId($client, $locationStatus, $site),
        ]);

        $message = 'Clocked in at ' . now()->format('H:i') . '.' . ($notice ? ' ' . $notice : '');

        return $request->wantsJson()
            ? response()->json([
                'time'            => now()->format('H:i'),
                'message'         => $message,
                'location_notice' => $notice,
                'off_site'        => $locationStatus === \App\Models\AttendanceLog::LOCATION_OUTSIDE,
            ])
            : back()->with($notice ? 'warning' : 'success', $message);
    }

    // =========================================================
    // CLOCK OUT
    // =========================================================
    public function clockOut(Request $request)
    {
        $employee = auth()->user()->employee;
        $log      = $employee
            ? AttendanceLog::where('employee_id', $employee->id)->whereDate('date', today())->first()
            : null;

        if (!$log || !$log->clock_in) {
            return $request->wantsJson()
                ? response()->json(['error' => 'Not clocked in.'], 422)
                : back()->with('error', 'You have not clocked in today.');
        }

        $lat = $request->lat !== null ? (float)$request->lat : null;
        $lng = $request->lng !== null ? (float)$request->lng : null;

        // Never refused: a refused clock-out strands an open row that only HR
        // can close, and the day's overtime is only written at clock-out, so it
        // is lost along with it.
        $client = $this->employeeClient($employee);
        [$outDistance, $locationStatus, $site] = $this->assessLocation($client, $lat, $lng);
        $notice = $this->locationNotice($client, $outDistance, $locationStatus, $site);

        $hours    = Carbon::parse($log->clock_in)->diffInMinutes(now()) / 60;
        $overtime = max(0, $hours - 8);

        // Calculate distance from clock-in point to clock-out point (if both exist)
        $distanceMetres = null;
        if ($lat !== null && $lng !== null && $log->lat !== null && $log->lng !== null) {
            $distanceMetres = round($this->distanceMetres(
                (float)$log->lat, (float)$log->lng,
                $lat, $lng
            ), 2);
        }

        $log->update([
            'clock_out'       => now(),
            'overtime_hours'  => round($overtime, 2),
            'clock_out_lat'   => $lat,
            'clock_out_lng'   => $lng,
            'distance_metres' => $distanceMetres,
        ]);

        $message = 'Clocked out at ' . now()->format('H:i') . '. Hours worked: ' . round($hours, 1) . 'h.'
                 . ($notice ? ' ' . $notice : '');

        return $request->wantsJson()
            ? response()->json([
                'time'            => now()->format('H:i'),
                'hours'           => round($hours, 1),
                'message'         => $message,
                'location_notice' => $notice,
                'off_site'        => $locationStatus === \App\Models\AttendanceLog::LOCATION_OUTSIDE,
            ])
            : back()->with($notice ? 'warning' : 'success', $message);
    }

    // =========================================================
    // CRUD — admin forms
    //
    // These routes come from Route::resource, which exposes them to every
    // signed-in role. Attendance decides how many days a casual worker is paid
    // for, so marking, editing and deleting are restricted here to the roles
    // that are accountable for it — an employee must not be able to edit their
    // own (or anyone else's) attendance record.
    // =========================================================
    private function authoriseManage(): void
    {
        abort_unless(
            auth()->user()->hasAnyRole(['super-admin', 'hr-admin', 'manager', 'account-manager']),
            403,
            'You are not allowed to change attendance records.'
        );
    }

    /** Account managers may only touch employees on the client sites they run. */
    private function authoriseEmployee(?Employee $employee): void
    {
        $this->authoriseManage();
        $clientIds = $this->visibleClientIds();
        if ($clientIds === null) return;               // admin — unrestricted

        abort_unless(
            $employee && $employee->clients()->whereIn('clients.id', $clientIds)->exists(),
            403,
            'That employee is not on one of your client sites.'
        );
    }

    private function manageableEmployees()
    {
        $clientIds = $this->visibleClientIds();

        return Employee::with('user')->where('status', 'active')
            ->when($clientIds !== null, fn($q) => $q->whereHas(
                'clients', fn($c) => $c->whereIn('clients.id', $clientIds)
            ))
            ->orderBy('first_name')->get();
    }

    public function create()
    {
        $this->authoriseManage();
        return view('attendance.create', ['employees' => $this->manageableEmployees()]);
    }

    public function store(Request $request)
    {
        $this->authoriseManage();
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date'        => 'required|date',
            'status'      => 'required|in:present,absent,late,half_day,leave',
        ]);
        $this->authoriseEmployee(Employee::find($request->employee_id));

        AttendanceLog::updateOrCreate(
            ['employee_id' => $request->employee_id, 'date' => $request->date],
            $request->only('clock_in', 'clock_out', 'status', 'overtime_hours')
                + ['client_id' => Employee::find($request->employee_id)?->clients()->value('clients.id')]
        );
        return redirect()->route('attendance.index')->with('success', 'Attendance recorded.');
    }

    public function show(AttendanceLog $attendance)
    {
        $attendance->load('employee.department', 'client');

        // An employee may open their own record; anyone else needs the rights.
        if ($attendance->employee_id !== auth()->user()->employee?->id) {
            $this->authoriseEmployee($attendance->employee);
        }

        return view('attendance.show', compact('attendance'));
    }

    public function edit(AttendanceLog $attendance)
    {
        $this->authoriseEmployee($attendance->employee);
        $employees = $this->manageableEmployees();
        return view('attendance.edit', compact('attendance', 'employees'));
    }

    public function update(Request $request, AttendanceLog $attendance)
    {
        $this->authoriseEmployee($attendance->employee);
        $request->validate(['status' => 'nullable|in:present,absent,late,half_day,leave']);
        $attendance->update($request->only('clock_in', 'clock_out', 'status', 'overtime_hours'));
        return redirect()->route('attendance.index')->with('success', 'Attendance updated.');
    }

    public function destroy(AttendanceLog $attendance)
    {
        $this->authoriseEmployee($attendance->employee);
        $attendance->delete();
        return back()->with('success', 'Deleted.');
    }

    // =========================================================
    // REPORTS / SHIFTS / HOLIDAYS
    // =========================================================
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
        $request->validate(['name' => 'required', 'start_time' => 'required', 'end_time' => 'required']);
        Shift::create($request->only('name', 'start_time', 'end_time', 'grace_minutes'));
        return back()->with('success', 'Shift created.');
    }

    public function holidays()
    {
        $holidays = Holiday::orderBy('date')->paginate(20);
        return view('attendance.holidays', compact('holidays'));
    }

    public function storeHoliday(Request $request)
    {
        $request->validate(['name' => 'required', 'date' => 'required|date']);
        Holiday::create($request->only('name', 'date', 'type'));
        return back()->with('success', 'Holiday added.');
    }
}
