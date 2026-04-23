@extends('layouts.app')
@section('title', 'Leave Approvals')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Leave Approvals</h1>
        <p class="text-slate-500 text-sm mt-1">Review and approve leave requests for your assigned staff</p>
    </div>
</div>

{{-- Filter --}}
<form method="GET" class="card p-4 mb-6 flex flex-wrap items-center gap-3">
    <select name="status" class="form-select w-48">
        <option value="">All Statuses</option>
        <option value="pending"  {{ request('status') == 'pending'  ? 'selected' : '' }}>Pending</option>
        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
    </select>
    <button type="submit" class="btn-primary">Filter</button>
    @if(request('status'))<a href="{{ route('client.leaves.index') }}" class="btn-secondary">Clear</a>@endif
</form>

<div class="card overflow-hidden">
    <table class="w-full">
        <thead class="table-header">
            <tr>
                <th>Employee</th>
                <th>Leave Type</th>
                <th>From</th>
                <th>To</th>
                <th>Days</th>
                <th>Applied</th>
                <th>HR Status</th>
                <th>Your Decision</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($leaves as $leave)
            <tr class="table-row">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <img src="{{ $leave->employee->user->avatar_url }}" class="w-8 h-8 rounded-full object-cover">
                        <div>
                            <p class="text-sm font-medium text-slate-800">{{ $leave->employee->full_name }}</p>
                            <p class="text-xs text-slate-400">{{ $leave->employee->emp_number }}</p>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ $leave->leaveType->name }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ $leave->from_date->format('d M Y') }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ $leave->to_date->format('d M Y') }}</td>
                <td class="px-4 py-3 text-sm font-semibold text-slate-700">{{ $leave->days_count }}</td>
                <td class="px-4 py-3 text-xs text-slate-400">{{ $leave->created_at->format('d M Y') }}</td>
                <td class="px-4 py-3">{!! $leave->status_badge !!}</td>
                <td class="px-4 py-3">
                    @if($leave->client_approval_status === 'approved')
                        <span class="badge-green"><i class="fas fa-check mr-1"></i>Approved</span>
                    @elseif($leave->client_approval_status === 'rejected')
                        <span class="badge-red"><i class="fas fa-times mr-1"></i>Rejected</span>
                    @else
                        <span class="badge-yellow">Pending</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('client.leaves.show', $leave) }}" class="btn-xs btn-blue">View</a>
                        @if(!$leave->client_approval_status || $leave->client_approval_status === 'pending')
                        <form method="POST" action="{{ route('client.leaves.approve', $leave) }}" onsubmit="return confirm('Approve this leave?')">
                            @csrf
                            <button type="submit" class="btn-xs btn-green">Approve</button>
                        </form>
                        <button onclick="showRejectModal({{ $leave->id }})" class="btn-xs" style="background:#fee2e2;color:#991b1b;">Reject</button>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="px-4 py-12 text-center text-slate-400">
                    <i class="fas fa-calendar-check text-4xl mb-3 block"></i>
                    No leave requests found
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $leaves->withQueryString()->links() }}</div>

{{-- Reject Modal --}}
<div id="reject-modal" class="fixed inset-0 bg-black/40 z-50 hidden items-center justify-center" onclick="closeRejectModal(event)">
    <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-xl">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Reject Leave Request</h3>
        <p class="text-sm text-slate-500 mb-4">Please confirm you want to reject this leave request.</p>
        <form id="reject-form" method="POST">
            @csrf
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeRejectModal()" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-danger">Reject</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function showRejectModal(id) {
    document.getElementById('reject-form').action = `/client/leaves/${id}/reject`;
    const m = document.getElementById('reject-modal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function closeRejectModal(e) {
    if (!e || e.target === document.getElementById('reject-modal')) {
        const m = document.getElementById('reject-modal');
        m.classList.add('hidden');
        m.classList.remove('flex');
    }
}
</script>
@endpush
@endsection
