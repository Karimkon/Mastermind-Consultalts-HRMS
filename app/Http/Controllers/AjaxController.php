<?php
namespace App\Http\Controllers;

use App\Models\{Employee, Department, AttendanceLog, Notification};
use Illuminate\Http\Request;
use Carbon\Carbon;

class AjaxController extends Controller
{
    public function searchEmployees(Request $request)
    {
        $employees = Employee::with(['department', 'designation'])
            ->where('status', 'active')
            ->where(function ($q) use ($request) {
                $q->whereHas('user', fn($u) => $u->where('name', 'like', "%{$request->q}%"))
                  ->orWhere('emp_number', 'like', "%{$request->q}%");
            })
            ->limit(15)->get()
            ->map(fn($e) => ['id' => $e->id, 'text' => $e->full_name.' ('.$e->emp_number.')', 'avatar' => $e->avatar_url]);
        return response()->json(['results' => $employees]);
    }

    public function notifications()
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->latest()->limit(10)->get();
        $unread = $notifications->whereNull('read_at')->count();
        return response()->json(['unread' => $unread, 'notifications' => $notifications]);
    }

    public function markNotificationsRead()
    {
        Notification::where('user_id', auth()->id())->whereNull('read_at')
            ->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function attendanceChart()
    {
        $days = collect(range(6, 0))->map(fn($i) => Carbon::today()->subDays($i));
        $data = $days->map(function ($day) {
            return [
                'date'    => $day->format('M d'),
                'present' => AttendanceLog::whereDate('date', $day)->where('status', 'present')->count(),
                'absent'  => AttendanceLog::whereDate('date', $day)->where('status', 'absent')->count(),
                'late'    => AttendanceLog::whereDate('date', $day)->where('status', 'late')->count(),
            ];
        });
        return response()->json($data);
    }

    public function headcountChart()
    {
        $data = Department::withCount(['employees' => fn($q) => $q->where('status', 'active')])
            ->having('employees_count', '>', 0)->get()
            ->map(fn($d) => ['label' => $d->name, 'count' => $d->employees_count]);
        return response()->json($data);
    }
}