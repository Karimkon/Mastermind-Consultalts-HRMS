<?php
namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;

class ClientLeaveController extends Controller
{
    private function getClient(): Client
    {
        return Client::where('user_id', auth()->id())->firstOrFail();
    }

    public function index(Request $request)
    {
        $client = $this->getClient();
        $employeeIds = $client->employees()->pluck('employees.id');

        $query = LeaveRequest::with(['employee', 'leaveType'])
            ->whereIn('employee_id', $employeeIds)
            ->where('client_approval_required', true);

        if ($request->status) {
            $query->where('client_approval_status', $request->status);
        } else {
            $query->whereIn('client_approval_status', ['pending', null])
                  ->orWhere(function ($q) use ($employeeIds) {
                      $q->whereIn('employee_id', $employeeIds)
                        ->where('client_approval_required', true)
                        ->whereNotNull('client_approval_status');
                  });
        }

        $leaves = LeaveRequest::with(['employee', 'leaveType'])
            ->whereIn('employee_id', $employeeIds)
            ->where('client_approval_required', true)
            ->when($request->status, fn($q) => $q->where('client_approval_status', $request->status))
            ->orderByRaw("FIELD(client_approval_status, 'pending', 'approved', 'rejected') ASC")
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('client.leaves.index', compact('client', 'leaves'));
    }

    public function show(LeaveRequest $leave)
    {
        $client = $this->getClient();
        $employeeIds = $client->employees()->pluck('employees.id');

        abort_unless($employeeIds->contains($leave->employee_id) && $leave->client_approval_required, 403);

        $leave->load(['employee', 'leaveType', 'approver']);

        return view('client.leaves.show', compact('client', 'leave'));
    }

    public function approve(LeaveRequest $leave)
    {
        $client = $this->getClient();
        $employeeIds = $client->employees()->pluck('employees.id');

        abort_unless($employeeIds->contains($leave->employee_id) && $leave->client_approval_required, 403);
        abort_if($leave->client_approval_status !== null && $leave->client_approval_status !== 'pending', 422);

        $leave->update([
            'client_approval_status' => 'approved',
            'client_approved_by'     => $client->id,
            'client_actioned_at'     => now(),
        ]);

        return redirect()->route('client.leaves.index')
            ->with('success', "Leave request for {$leave->employee->full_name} approved.");
    }

    public function reject(Request $request, LeaveRequest $leave)
    {
        $client = $this->getClient();
        $employeeIds = $client->employees()->pluck('employees.id');

        abort_unless($employeeIds->contains($leave->employee_id) && $leave->client_approval_required, 403);
        abort_if($leave->client_approval_status !== null && $leave->client_approval_status !== 'pending', 422);

        $leave->update([
            'client_approval_status' => 'rejected',
            'client_approved_by'     => $client->id,
            'client_actioned_at'     => now(),
        ]);

        return redirect()->route('client.leaves.index')
            ->with('success', "Leave request for {$leave->employee->full_name} rejected.");
    }
}
