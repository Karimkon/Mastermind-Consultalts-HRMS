@extends("layouts.app")
@section("title","Leave Requests")
@section("content")
<x-page-header title="Leave Management" subtitle="View and manage leave requests">
    <a href="{{ route('leaves.create') }}" class="btn-primary"><i class="fas fa-plus"></i> Apply for Leave</a>
    <a href="{{ route('leaves.types') }}" class="btn-secondary"><i class="fas fa-cog"></i> Leave Types</a>
</x-page-header>

<x-filter-bar :action="route('leaves.index')">
    <div><label class="form-label">Status</label>
        <select name="status" class="form-select w-36">
            <option value="">All</option>
            @foreach(["pending"=>"Pending","approved"=>"Approved","rejected"=>"Rejected","cancelled"=>"Cancelled"] as $v=>$l)<option value="{{ $v }}" {{ request('status')==$v?'selected':'' }}>{{ $l }}</option>@endforeach
        </select>
    </div>
    <div><label class="form-label">Leave Type</label>
        <select name="leave_type_id" class="form-select select2 w-44">
            <option value="">All Types</option>
            @foreach($leaveTypes as $lt)<option value="{{ $lt->id }}" {{ request('leave_type_id')==$lt->id?'selected':'' }}>{{ $lt->name }}</option>@endforeach
        </select>
    </div>
    <div><label class="form-label">Department</label>
        <select name="department_id" class="form-select select2 w-44">
            <option value="">All Departments</option>
            @foreach($departments as $d)<option value="{{ $d->id }}" {{ request('department_id')==$d->id?'selected':'' }}>{{ $d->name }}</option>@endforeach
        </select>
    </div>
</x-filter-bar>

<x-data-table>
    <thead class="bg-slate-50"><tr>
        <th class="table-head px-6 py-3 text-left">Employee</th>
        <th class="table-head px-4 py-3 text-left">Leave Type</th>
        <th class="table-head px-4 py-3 text-left">From</th>
        <th class="table-head px-4 py-3 text-left">To</th>
        <th class="table-head px-4 py-3 text-center">Days</th>
        <th class="table-head px-4 py-3 text-left">Applied</th>
        <th class="table-head px-4 py-3 text-left">Status</th>
        <th class="table-head px-4 py-3 text-left">Actions</th>
    </tr></thead>
    <tbody class="divide-y divide-slate-100">
        @forelse($requests as $leave)
        <tr class="table-row">
            <td class="px-6 py-3"><div class="flex items-center gap-3"><img src="{{ $leave->employee->avatar_url }}" class="w-8 h-8 rounded-full"><p class="text-sm font-medium text-slate-800">{{ $leave->employee->full_name }}</p></div></td>
            <td class="px-4 py-3"><span class="badge-blue">{{ $leave->leaveType->name }}</span></td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $leave->from_date->format('M d, Y') }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $leave->to_date->format('M d, Y') }}</td>
            <td class="px-4 py-3 text-center text-sm font-semibold text-slate-800">{{ $leave->days_count }}</td>
            <td class="px-4 py-3 text-sm text-slate-500">{{ $leave->created_at->format('M d') }}</td>
            <td class="px-4 py-3">{!! $leave->status_badge !!}</td>
            <td class="px-4 py-3">
                <div class="flex items-center gap-1">
                    <a href="{{ route('leaves.show',$leave) }}" class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"><i class="fas fa-eye text-xs"></i></a>
                    @if($leave->status === 'pending')
                    @can("leave.approve")
                    <form method="POST" action="{{ route('leaves.approve',$leave) }}" class="inline">@csrf<button class="p-1.5 text-slate-400 hover:text-green-600 hover:bg-green-50 rounded-lg" title="Approve"><i class="fas fa-check text-xs"></i></button></form>
                    <button onclick="showRejectModal({{ $leave->id }})" class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg" title="Reject"><i class="fas fa-times text-xs"></i></button>
                    @endcan
                    @endif
                </div>
            </td>
        </tr>
        @empty
        <tr><td colspan="8" class="py-12 text-center text-slate-400">No leave requests found</td></tr>
        @endforelse
    </tbody>
</x-data-table>
<div class="mt-4">{{ $requests->withQueryString()->links() }}</div>

{{-- Reject Modal --}}
<div id="rejectModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
        <h3 class="font-semibold text-slate-800 mb-4">Reject Leave Request</h3>
        <form id="rejectForm" method="POST">@csrf
            <div><label class="form-label">Reason for Rejection *</label><textarea name="rejection_reason" class="form-input" rows="3" required></textarea></div>
            <div class="flex gap-3 mt-4"><button type="submit" class="btn-danger"><i class="fas fa-times"></i> Reject</button><button type="button" onclick="document.getElementById('rejectModal').classList.add('hidden')" class="btn-secondary">Cancel</button></div>
        </form>
    </div>
</div>
@endsection
@push("scripts")
<script>
function showRejectModal(id) {
    document.getElementById("rejectForm").action = "/leaves/" + id + "/reject";
    document.getElementById("rejectModal").classList.remove("hidden");
}
</script>
@endpush
