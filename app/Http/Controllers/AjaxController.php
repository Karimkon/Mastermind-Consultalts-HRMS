<?php
namespace App\Http\Controllers;

use App\Models\{Employee, Department, AttendanceLog, Notification};
use Illuminate\Http\Request;
use Carbon\Carbon;

class AjaxController extends Controller
{
    public function searchEmployees(Request $request)
    {
        $term = trim((string) $request->q);

        $employees = Employee::with(['department', 'designation'])
            ->where('status', 'active')
            ->where(function ($q) use ($term) {
                // first_name / last_name are the authoritative names: the linked
                // user account is optional, and 905 staff were imported with
                // their names on the employee row. Searching only the user name
                // hid everybody without a login, and half-matched the rest.
                $q->where('first_name', 'like', "%{$term}%")
                  ->orWhere('last_name', 'like', "%{$term}%")
                  ->orWhere('emp_number', 'like', "%{$term}%")
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$term}%"));

                // A full name like "Florence Nakibuuka" spans two columns. Each
                // word is matched against either of them rather than building a
                // CONCAT, which MySQL has and SQLite does not - and this also
                // matches the name typed in the other order, which people do.
                $words = preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];

                if (count($words) > 1) {
                    $q->orWhere(function ($all) use ($words) {
                        foreach ($words as $word) {
                            $all->where(function ($either) use ($word) {
                                $either->where('first_name', 'like', "%{$word}%")
                                       ->orWhere('last_name', 'like', "%{$word}%");
                            });
                        }
                    });
                }
            })
            // You cannot cover for yourself.
            ->when($request->boolean('exclude_self') && auth()->user()?->employee,
                fn($q) => $q->whereKeyNot(auth()->user()->employee->id))
            ->orderBy('first_name')
            ->limit(15)->get()
            // Contact details are deliberately not returned. This endpoint is open
            // to every signed-in user, and the server resolves the chosen person's
            // email itself rather than handing out 905 addresses to anyone who
            // types into a search box.
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