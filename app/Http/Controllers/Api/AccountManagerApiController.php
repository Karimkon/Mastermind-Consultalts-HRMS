<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Client, Employee, LeaveRequest};
use Illuminate\Http\Request;

class AccountManagerApiController extends Controller
{
    private function managedClients()
    {
        $q = Client::with(['employees.department', 'employees.designation', 'employees.user']);
        if (!auth()->user()->hasAnyRole(['super-admin', 'hr-admin'])) {
            $q->where('account_manager_id', auth()->id());
        }
        return $q->get();
    }

    private function managedEmployeeIds(?int $clientId = null): array
    {
        $clients = $clientId
            ? $this->managedClients()->where('id', $clientId)
            : $this->managedClients();
        return $clients->flatMap(fn($c) => $c->employees->pluck('id'))->unique()->values()->toArray();
    }

    /* ── Clients list ── */
    public function clients()
    {
        $clients = $this->managedClients()->map(fn($c) => [
            'id'             => $c->id,
            'company_name'   => $c->company_name,
            'status'         => $c->status,
            'employee_count' => $c->employees->count(),
        ]);
        return response()->json($clients);
    }

    /* ── Employees ── */
    public function employees(Request $request)
    {
        $clientId = $request->client_id ? (int) $request->client_id : null;
        $empIds   = $this->managedEmployeeIds($clientId);
        $clients  = $this->managedClients();

        $q = Employee::with(['department', 'designation', 'user'])
            ->whereIn('id', $empIds)
            ->when($request->search, fn($q) => $q->where(function ($q2) use ($request) {
                $q2->where('first_name', 'like', "%{$request->search}%")
                   ->orWhere('last_name',  'like', "%{$request->search}%")
                   ->orWhere('emp_number', 'like', "%{$request->search}%");
            }))
            ->when($request->status, fn($q) => $q->where('status', $request->status));

        $paginated = $q->paginate(20);

        return response()->json([
            'data' => $paginated->map(fn($emp) => [
                'id'          => $emp->id,
                'emp_number'  => $emp->emp_number,
                'full_name'   => $emp->full_name,
                'email'       => $emp->user?->email ?? '',
                'phone'       => $emp->phone ?? '',
                'department'  => $emp->department?->name ?? '—',
                'designation' => $emp->designation?->name ?? '—',
                'status'      => $emp->status,
                'hire_date'   => $emp->hire_date,
                'avatar_url'  => $emp->avatar_url,
                'company'     => $clients->first(fn($c) => $c->employees->contains('id', $emp->id))?->company_name ?? '—',
            ]),
            'total'        => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
        ]);
    }

    /* ── Leaves ── */
    public function leaves(Request $request)
    {
        $clientId = $request->client_id ? (int) $request->client_id : null;
        $empIds   = $this->managedEmployeeIds($clientId);

        $paginated = LeaveRequest::with(['employee.department', 'leaveType'])
            ->whereIn('employee_id', $empIds)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'data' => $paginated->map(fn($l) => [
                'id'               => $l->id,
                'employee_id'      => $l->employee_id,
                'employee'         => $l->employee->full_name,
                'avatar_url'       => $l->employee->avatar_url,
                'department'       => $l->employee->department?->name ?? '—',
                'leave_type'       => $l->leaveType?->name ?? '—',
                'from_date'        => $l->from_date?->format('Y-m-d'),
                'to_date'          => $l->to_date?->format('Y-m-d'),
                'days_count'       => $l->days_count,
                'reason'           => $l->reason,
                'status'           => $l->status,
                'replacement_name' => $l->replacement_name,
            ]),
            'total'        => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
        ]);
    }

    /* ── Approve leave ── */
    public function approveLeave(LeaveRequest $leave)
    {
        abort_unless(in_array($leave->employee_id, $this->managedEmployeeIds()), 403);
        $leave->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        $leave->employee->update(['status' => 'on_leave']);
        return response()->json(['message' => 'Leave approved.', 'status' => 'approved']);
    }

    /* ── Reject leave ── */
    public function rejectLeave(Request $request, LeaveRequest $leave)
    {
        abort_unless(in_array($leave->employee_id, $this->managedEmployeeIds()), 403);
        $leave->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->reason ?? 'Rejected by Account Manager',
            'approved_by'      => auth()->id(),
            'approved_at'      => now(),
        ]);
        return response()->json(['message' => 'Leave rejected.', 'status' => 'rejected']);
    }
}
