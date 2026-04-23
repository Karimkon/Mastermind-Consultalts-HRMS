<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{LeaveRequest, LeaveType, LeaveBalance, Employee};
use Illuminate\Http\Request;
use Carbon\Carbon;

class LeaveApiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = LeaveRequest::with(['employee.user', 'leaveType']);

        if ($user->hasRole('employee') && !$user->hasRole(['super-admin','hr-admin','manager'])) {
            $query->where('employee_id', $user->employee?->id);
        }

        if ($request->status)      $query->where('status', $request->status);
        if ($request->employee_id) $query->where('employee_id', $request->employee_id);
        if ($request->type_id)     $query->where('leave_type_id', $request->type_id);

        return response()->json([
            'data' => $query->latest()->paginate(20)->through(fn($l) => $this->format($l)),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'from_date'     => 'required|date|after_or_equal:today',
            'to_date'       => 'required|date|after_or_equal:from_date',
            'reason'        => 'required|string|max:500',
        ]);

        $employee = $request->user()->employee;
        if (!$employee) return response()->json(['message' => 'No employee profile.'], 422);

        $days = $this->countWorkDays($request->from_date, $request->to_date);

        $hasClient = $employee->clients()->exists();

        $leave = LeaveRequest::create([
            'employee_id'              => $employee->id,
            'leave_type_id'            => $request->leave_type_id,
            'from_date'                => $request->from_date,
            'to_date'                  => $request->to_date,
            'days_count'               => $days,
            'reason'                   => $request->reason,
            'status'                   => 'pending',
            'client_approval_required' => $hasClient,
            'client_approval_status'   => $hasClient ? 'pending' : null,
        ]);

        // Update balance pending_days
        LeaveBalance::where('employee_id', $employee->id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('year', Carbon::now()->year)
            ->increment('pending_days', $days);

        return response()->json(['data' => $this->format($leave->load('employee.user', 'leaveType'))], 201);
    }

    public function show(LeaveRequest $leave)
    {
        $this->authorizeLeave($leave);
        return response()->json(['data' => $this->format($leave->load('employee.user', 'leaveType', 'approver'))]);
    }

    public function update(Request $request, LeaveRequest $leave)
    {
        $this->authorizeLeave($leave);
        if ($leave->status !== 'pending') return response()->json(['message' => 'Cannot edit non-pending leave.'], 422);

        $request->validate([
            'from_date' => 'required|date',
            'to_date'   => 'required|date|after_or_equal:from_date',
            'reason'    => 'required|string',
        ]);

        $days = $this->countWorkDays($request->from_date, $request->to_date);
        $oldDays = $leave->days_count;

        $leave->update([
            'from_date'  => $request->from_date,
            'to_date'    => $request->to_date,
            'days_count' => $days,
            'reason'     => $request->reason,
        ]);

        // Adjust balance
        $diff = $days - $oldDays;
        if ($diff !== 0) {
            LeaveBalance::where('employee_id', $leave->employee_id)
                ->where('leave_type_id', $leave->leave_type_id)
                ->where('year', Carbon::now()->year)
                ->increment('pending_days', $diff);
        }

        return response()->json(['data' => $this->format($leave->fresh()->load('employee.user', 'leaveType'))]);
    }

    public function destroy(LeaveRequest $leave)
    {
        $this->authorizeLeave($leave);
        $leave->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    public function approve(LeaveRequest $leave)
    {
        $user = request()->user();
        if (!$user->hasRole(['super-admin','hr-admin','manager'])) abort(403);
        if ($leave->status !== 'pending') return response()->json(['message' => 'Leave is not pending.'], 422);

        $leave->update([
            'status'      => 'approved',
            'approved_by' => $user->employee?->id,
            'actioned_at' => now(),
        ]);

        // Update balance
        LeaveBalance::where('employee_id', $leave->employee_id)
            ->where('leave_type_id', $leave->leave_type_id)
            ->where('year', Carbon::now()->year)
            ->decrement('pending_days', $leave->days_count);
        LeaveBalance::where('employee_id', $leave->employee_id)
            ->where('leave_type_id', $leave->leave_type_id)
            ->where('year', Carbon::now()->year)
            ->increment('used_days', $leave->days_count);

        Employee::where('id', $leave->employee_id)->update(['status' => 'on_leave']);

        return response()->json(['data' => $this->format($leave->fresh()->load('employee.user', 'leaveType'))]);
    }

    public function reject(Request $request, LeaveRequest $leave)
    {
        $user = $request->user();
        if (!$user->hasRole(['super-admin','hr-admin','manager'])) abort(403);
        if ($leave->status !== 'pending') return response()->json(['message' => 'Leave is not pending.'], 422);

        $leave->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->reason,
            'approved_by'      => $user->employee?->id,
            'actioned_at'      => now(),
        ]);

        LeaveBalance::where('employee_id', $leave->employee_id)
            ->where('leave_type_id', $leave->leave_type_id)
            ->where('year', Carbon::now()->year)
            ->decrement('pending_days', $leave->days_count);

        return response()->json(['data' => $this->format($leave->fresh())]);
    }

    public function cancel(LeaveRequest $leave)
    {
        $this->authorizeLeave($leave);
        if (!in_array($leave->status, ['pending','approved'])) {
            return response()->json(['message' => 'Cannot cancel.'], 422);
        }

        if ($leave->status === 'approved') {
            LeaveBalance::where('employee_id', $leave->employee_id)
                ->where('leave_type_id', $leave->leave_type_id)
                ->where('year', Carbon::now()->year)
                ->decrement('used_days', $leave->days_count);
        } else {
            LeaveBalance::where('employee_id', $leave->employee_id)
                ->where('leave_type_id', $leave->leave_type_id)
                ->where('year', Carbon::now()->year)
                ->decrement('pending_days', $leave->days_count);
        }

        $leave->update(['status' => 'cancelled']);
        return response()->json(['data' => $this->format($leave->fresh())]);
    }

    public function types()
    {
        return response()->json([
            'data' => LeaveType::where('is_active', true)->get()->map(fn($t) => [
                'id'           => $t->id,
                'name'         => $t->name,
                'code'         => $t->code,
                'days_allowed' => $t->days_allowed,
                'is_paid'      => $t->is_paid,
                'color'        => $t->color,
            ]),
        ]);
    }

    public function balance(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) return response()->json(['data' => []]);

        $balances = LeaveBalance::with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('year', $request->year ?? Carbon::now()->year)
            ->get()->map(fn($b) => [
                'id'          => $b->id,
                'type'        => $b->leaveType->name,
                'color'       => $b->leaveType->color,
                'total_days'  => $b->total_days,
                'used_days'   => $b->used_days,
                'pending_days'=> $b->pending_days,
                'remaining'   => max(0, $b->total_days - $b->used_days - $b->pending_days),
            ]);

        return response()->json(['data' => $balances]);
    }

    private function format(LeaveRequest $l): array
    {
        return [
            'id'                       => $l->id,
            'employee_id'              => $l->employee_id,
            'employee_name'            => $l->employee?->full_name,
            'employee_avatar'          => $l->employee?->user?->avatar_url,
            'leave_type'               => $l->leaveType?->name,
            'leave_type_color'         => $l->leaveType?->color,
            'from_date'                => $l->from_date?->format('Y-m-d'),
            'to_date'                  => $l->to_date?->format('Y-m-d'),
            'days_count'               => $l->days_count,
            'reason'                   => $l->reason,
            'status'                   => $l->status,
            'rejection_reason'         => $l->rejection_reason,
            'approved_by'              => $l->approver?->full_name,
            'client_approval_required' => $l->client_approval_required,
            'client_approval_status'   => $l->client_approval_status,
            'created_at'               => $l->created_at?->format('Y-m-d'),
        ];
    }

    private function authorizeLeave(LeaveRequest $leave): void
    {
        $user = request()->user();
        if ($user->hasRole(['super-admin','hr-admin','manager'])) return;
        if ($user->employee?->id === $leave->employee_id) return;
        abort(403);
    }

    private function countWorkDays(string $from, string $to): int
    {
        $start = Carbon::parse($from);
        $end   = Carbon::parse($to);
        $days  = 0;
        while ($start->lte($end)) {
            if ($start->isWeekday()) $days++;
            $start->addDay();
        }
        return max(1, $days);
    }
}
