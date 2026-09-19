<?php
namespace App\Http\Controllers;

use App\Models\{AttendanceLog, Client, Employee};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * HR / Admin sign-off on overtime before payroll pays it.
 *
 * Policy is 2 hours a day and 12 a week, but those are shown as warnings rather
 * than enforced: management asked to see the true recorded hours and keep the
 * discretion to approve more. Payroll pays approved_overtime_hours only.
 */
class OvertimeApprovalController extends Controller
{
    private function authorise(): void
    {
        abort_unless(
            auth()->user()->hasAnyRole(['super-admin', 'hr-admin', 'manager']),
            403,
            'Only HR or an administrator can approve overtime.'
        );
    }

    public function index(Request $request)
    {
        $this->authorise();

        $status = $request->status ?: 'pending';
        $from   = $request->date_from ?: now()->startOfMonth()->toDateString();
        $to     = $request->date_to   ?: now()->endOfMonth()->toDateString();

        $logs = AttendanceLog::with(['employee.department', 'client', 'overtimeApprover'])
            ->where('overtime_hours', '>', 0)
            ->when($status !== 'all', fn($q) => $q->where('overtime_status', $status))
            ->whereBetween('date', [$from, $to])
            ->when($request->client_id, fn($q) => $q->whereHas(
                'employee.clients', fn($c) => $c->where('clients.id', $request->client_id)
            ))
            ->when($request->employee_id, fn($q) => $q->where('employee_id', $request->employee_id))
            ->orderByDesc('date')
            ->paginate(40)->withQueryString();

        // Weekly totals per employee, so the 12-hour policy can be judged in
        // context rather than one day at a time.
        $weekTotals = [];
        foreach ($logs as $log) {
            $key = $log->employee_id . '|' . Carbon::parse($log->date)->startOfWeek()->toDateString();
            if (!isset($weekTotals[$key])) {
                $weekTotals[$key] = (float) AttendanceLog::where('employee_id', $log->employee_id)
                    ->whereBetween('date', [
                        Carbon::parse($log->date)->startOfWeek()->toDateString(),
                        Carbon::parse($log->date)->endOfWeek()->toDateString(),
                    ])->sum('overtime_hours');
            }
        }

        $pendingCount = AttendanceLog::awaitingOvertimeApproval()->count();

        return view('overtime.index', [
            'logs'         => $logs,
            'weekTotals'   => $weekTotals,
            'clients'      => Client::orderBy('company_name')->get(),
            'status'       => $status,
            'from'         => $from,
            'to'           => $to,
            'pendingCount' => $pendingCount,
            'dailyCap'     => AttendanceLog::DAILY_OVERTIME_CAP,
            'weeklyCap'    => AttendanceLog::WEEKLY_OVERTIME_CAP,
        ]);
    }

    /** Approve one record, optionally for fewer hours than were recorded. */
    public function approve(Request $request, AttendanceLog $log)
    {
        $this->authorise();
        $data = $request->validate([
            'hours' => 'required|numeric|min:0|max:24',
            'note'  => 'nullable|string|max:255',
        ]);

        $log->update([
            'approved_overtime_hours' => $data['hours'],
            'overtime_status'         => $data['hours'] > 0 ? 'approved' : 'rejected',
            'overtime_approved_by'    => auth()->id(),
            'overtime_approved_at'    => now(),
            'overtime_note'           => $data['note'] ?? null,
        ]);

        return back()->with('success',
            $data['hours'] > 0
                ? "Approved {$data['hours']}h overtime for {$log->employee->full_name}."
                : "Overtime rejected for {$log->employee->full_name}.");
    }

    public function reject(Request $request, AttendanceLog $log)
    {
        $this->authorise();
        $request->validate(['note' => 'nullable|string|max:255']);

        $log->update([
            'approved_overtime_hours' => 0,
            'overtime_status'         => 'rejected',
            'overtime_approved_by'    => auth()->id(),
            'overtime_approved_at'    => now(),
            'overtime_note'           => $request->note,
        ]);

        return back()->with('success', "Overtime rejected for {$log->employee->full_name}.");
    }

    /**
     * Approve every record in the current filter at the policy cap.
     * Capping rather than approving the raw figure keeps a bulk action from
     * quietly authorising a 6-hour day nobody looked at.
     */
    public function bulkApprove(Request $request)
    {
        $this->authorise();
        $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer',
        ]);

        $cap  = AttendanceLog::DAILY_OVERTIME_CAP;
        $logs = AttendanceLog::whereIn('id', $request->ids)->where('overtime_status', 'pending')->get();

        foreach ($logs as $log) {
            $log->update([
                'approved_overtime_hours' => min((float) $log->overtime_hours, $cap),
                'overtime_status'         => 'approved',
                'overtime_approved_by'    => auth()->id(),
                'overtime_approved_at'    => now(),
                'overtime_note'           => 'Bulk approved at the ' . $cap . 'h daily policy cap.',
            ]);
        }

        return back()->with('success',
            $logs->count() . " record(s) approved, each capped at {$cap}h. Approve individually to allow more.");
    }
}
