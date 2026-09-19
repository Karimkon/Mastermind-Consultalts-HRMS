<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Mobile endpoints for overtime sign-off.
 *
 * Payroll pays approved_overtime_hours only, so these are the endpoints that
 * decide whether overtime is paid at all.
 */
class OvertimeApiController extends Controller
{
    private function authorise(Request $request): void
    {
        abort_unless(
            $request->user()->hasAnyRole(['super-admin', 'hr-admin', 'manager']),
            403,
            'Only HR or an administrator can approve overtime.'
        );
    }

    private function format(AttendanceLog $log): array
    {
        $weekStart = Carbon::parse($log->date)->startOfWeek();
        $weekTotal = (float) AttendanceLog::where('employee_id', $log->employee_id)
            ->whereBetween('date', [$weekStart->toDateString(), $weekStart->copy()->endOfWeek()->toDateString()])
            ->sum('overtime_hours');

        return [
            'id'              => $log->id,
            'employee_id'     => $log->employee_id,
            'employee_name'   => $log->employee?->full_name,
            'emp_number'      => $log->employee?->emp_number,
            'client_name'     => $log->client?->company_name ?? $log->employee?->clients->first()?->company_name,
            'date'            => $log->date?->toDateString(),
            'recorded_hours'  => (float) $log->overtime_hours,
            'approved_hours'  => (float) $log->approved_overtime_hours,
            'status'          => $log->overtime_status,
            'approver'        => $log->overtimeApprover?->name,
            'approved_at'     => $log->overtime_approved_at?->toIso8601String(),
            'note'            => $log->overtime_note,
            'week_total'      => round($weekTotal, 2),
            'exceeds_daily'   => $log->exceedsDailyCap(),
            'exceeds_weekly'  => $weekTotal > AttendanceLog::WEEKLY_OVERTIME_CAP,
        ];
    }

    public function index(Request $request)
    {
        $this->authorise($request);

        $status = $request->status ?: 'pending';
        $from   = $request->date_from ?: now()->startOfMonth()->toDateString();
        $to     = $request->date_to   ?: now()->endOfMonth()->toDateString();

        $logs = AttendanceLog::with(['employee.clients', 'client', 'overtimeApprover'])
            ->where('overtime_hours', '>', 0)
            ->when($status !== 'all', fn($q) => $q->where('overtime_status', $status))
            ->whereBetween('date', [$from, $to])
            ->when($request->client_id, fn($q) => $q->whereHas(
                'employee.clients', fn($c) => $c->where('clients.id', $request->client_id)
            ))
            ->orderByDesc('date')
            ->paginate(30);

        return response()->json([
            'data'  => $logs->through(fn($l) => $this->format($l))->items(),
            'meta'  => [
                'current_page'  => $logs->currentPage(),
                'last_page'     => $logs->lastPage(),
                'total'         => $logs->total(),
                'pending_total' => AttendanceLog::awaitingOvertimeApproval()->count(),
            ],
            'policy' => [
                'daily_cap'  => AttendanceLog::DAILY_OVERTIME_CAP,
                'weekly_cap' => AttendanceLog::WEEKLY_OVERTIME_CAP,
            ],
        ]);
    }

    public function approve(Request $request, AttendanceLog $log)
    {
        $this->authorise($request);
        $data = $request->validate([
            'hours' => 'required|numeric|min:0|max:24',
            'note'  => 'nullable|string|max:255',
        ]);

        $log->update([
            'approved_overtime_hours' => $data['hours'],
            'overtime_status'         => $data['hours'] > 0 ? 'approved' : 'rejected',
            'overtime_approved_by'    => $request->user()->id,
            'overtime_approved_at'    => now(),
            'overtime_note'           => $data['note'] ?? null,
        ]);

        return response()->json([
            'message' => $data['hours'] > 0
                ? "Approved {$data['hours']}h overtime."
                : 'Overtime rejected.',
            'data'    => $this->format($log->fresh()->load(['employee.clients', 'client', 'overtimeApprover'])),
        ]);
    }

    public function reject(Request $request, AttendanceLog $log)
    {
        $this->authorise($request);
        $request->validate(['note' => 'nullable|string|max:255']);

        $log->update([
            'approved_overtime_hours' => 0,
            'overtime_status'         => 'rejected',
            'overtime_approved_by'    => $request->user()->id,
            'overtime_approved_at'    => now(),
            'overtime_note'           => $request->note,
        ]);

        return response()->json([
            'message' => 'Overtime rejected.',
            'data'    => $this->format($log->fresh()->load(['employee.clients', 'client', 'overtimeApprover'])),
        ]);
    }
}
