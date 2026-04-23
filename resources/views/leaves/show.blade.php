@extends("layouts.app")
@section("title","Leave Request")
@section("content")
<x-page-header title="Leave Request Details">
    <a href="{{ route('leaves.index') }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</x-page-header>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 card p-6">
        <div class="flex items-start justify-between mb-6">
            <div class="flex items-center gap-4">
                <img src="{{ $leave->employee->avatar_url }}" class="w-14 h-14 rounded-xl object-cover border-2 border-slate-200">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">{{ $leave->employee->full_name }}</h3>
                    <p class="text-sm text-slate-500">{{ $leave->employee->designation?->title }}</p>
                </div>
            </div>
            {!! $leave->status_badge !!}
        </div>
        <div class="grid grid-cols-2 gap-4 mb-6">
            @foreach(["Leave Type" => $leave->leaveType->name, "From Date" => $leave->from_date->format('M d, Y'), "To Date" => $leave->to_date->format('M d, Y'), "Days Requested" => $leave->days_count . ' day(s)', "Applied On" => $leave->created_at->format('M d, Y H:i'), "Approved By" => $leave->approver?->name ?? '—'] as $label => $val)
            <div class="bg-slate-50 rounded-lg p-3">
                <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">{{ $label }}</p>
                <p class="text-sm font-semibold text-slate-800">{{ $val }}</p>
            </div>
            @endforeach
        </div>
        <div class="mb-4">
            <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Reason</p>
            <p class="text-sm text-slate-800 bg-slate-50 rounded-lg p-3">{{ $leave->reason }}</p>
        </div>
        @if($leave->rejection_reason)
        <div class="bg-red-50 border border-red-200 rounded-lg p-3">
            <p class="text-xs font-semibold text-red-600 uppercase mb-1">Rejection Reason</p>
            <p class="text-sm text-red-800">{{ $leave->rejection_reason }}</p>
        </div>
        @endif
        @if($leave->client_approval_required)
        <div class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-3">
            <p class="text-xs font-semibold text-blue-700 uppercase mb-1">Client Approval Status</p>
            @if($leave->client_approval_status === 'approved')
                <span class="badge-green"><i class="fas fa-check mr-1"></i>Client Approved</span>
            @elseif($leave->client_approval_status === 'rejected')
                <span class="badge-red"><i class="fas fa-times mr-1"></i>Client Rejected</span>
            @else
                <span class="badge-yellow">Awaiting client decision</span>
            @endif
            @if($leave->clientApprover)
            <p class="text-xs text-blue-500 mt-1">by {{ $leave->clientApprover->company_name }}</p>
            @endif
        </div>
        @endif
    </div>
    <div class="space-y-4">
        @if($leave->status === 'pending')
        @can("leave.approve")
        <div class="card p-4">
            <form method="POST" action="{{ route('leaves.approve',$leave) }}" class="mb-3">@csrf
                <button class="btn-primary w-full justify-center"><i class="fas fa-check"></i> Approve Leave</button>
            </form>
            <form method="POST" action="{{ route('leaves.reject',$leave) }}">@csrf
                <textarea name="rejection_reason" class="form-input mb-2" rows="2" placeholder="Rejection reason..." required></textarea>
                <button class="btn-danger w-full justify-center"><i class="fas fa-times"></i> Reject Leave</button>
            </form>
        </div>
        @endcan
        @endif
        @if(in_array($leave->status, ["pending","approved"]))
        <form method="POST" action="{{ route('leaves.cancel',$leave) }}" onsubmit="return confirm('Cancel this leave?')">@csrf
            <button class="btn-secondary w-full justify-center"><i class="fas fa-ban"></i> Cancel Request</button>
        </form>
        @endif
    </div>
</div>
@endsection
