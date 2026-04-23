@extends('layouts.app')
@section('title', 'Leave Request Detail')

@section('content')
<div class="flex items-center gap-4 mb-6">
    <a href="{{ route('client.leaves.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-2"></i>Back</a>
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Leave Request Detail</h1>
        <p class="text-slate-500 text-sm">Submitted {{ $leave->created_at->format('d M Y') }}</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Employee info --}}
    <div class="card p-6">
        <div class="flex flex-col items-center text-center">
            <img src="{{ $leave->employee->user->avatar_url }}" class="w-20 h-20 rounded-full object-cover border-4 border-blue-100 mb-3">
            <h3 class="font-semibold text-slate-800">{{ $leave->employee->full_name }}</h3>
            <p class="text-sm text-slate-500">{{ $leave->employee->emp_number }}</p>
            @if($leave->employee->department)
            <p class="text-xs text-slate-400 mt-1">{{ $leave->employee->department->name }}</p>
            @endif
        </div>
        <hr class="my-4 border-slate-100">
        <div class="space-y-3 text-sm">
            <div class="flex justify-between">
                <span class="text-slate-500">Leave Type</span>
                <span class="font-medium text-slate-800">{{ $leave->leaveType->name }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">From Date</span>
                <span class="font-medium text-slate-800">{{ $leave->from_date->format('d M Y') }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">To Date</span>
                <span class="font-medium text-slate-800">{{ $leave->to_date->format('d M Y') }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Days Requested</span>
                <span class="font-bold text-slate-800">{{ $leave->days_count }}</span>
            </div>
        </div>
    </div>

    {{-- Detail & actions --}}
    <div class="lg:col-span-2 space-y-6">
        <div class="card p-6">
            <h3 class="font-semibold text-slate-700 mb-3">Reason</h3>
            <p class="text-sm text-slate-600 bg-slate-50 rounded-lg p-4">{{ $leave->reason ?: 'No reason provided.' }}</p>
        </div>

        <div class="card p-6">
            <h3 class="font-semibold text-slate-700 mb-4">Approval Status</h3>
            <div class="grid grid-cols-2 gap-4">
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
                    <p class="text-xs text-slate-500 mb-1">HR / Internal Status</p>
                    {!! $leave->status_badge !!}
                    @if($leave->approver)
                    <p class="text-xs text-slate-400 mt-1">by {{ $leave->approver->full_name }}</p>
                    @endif
                </div>
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
                    <p class="text-xs text-slate-500 mb-1">Your Decision</p>
                    @if($leave->client_approval_status === 'approved')
                        <span class="badge-green"><i class="fas fa-check mr-1"></i>Approved</span>
                    @elseif($leave->client_approval_status === 'rejected')
                        <span class="badge-red"><i class="fas fa-times mr-1"></i>Rejected</span>
                    @else
                        <span class="badge-yellow">Awaiting your decision</span>
                    @endif
                    @if($leave->client_actioned_at)
                    <p class="text-xs text-slate-400 mt-1">on {{ $leave->client_actioned_at->format('d M Y H:i') }}</p>
                    @endif
                </div>
            </div>

            @if(!$leave->client_approval_status || $leave->client_approval_status === 'pending')
            <div class="flex items-center gap-3 mt-6 pt-4 border-t border-slate-100">
                <form method="POST" action="{{ route('client.leaves.approve', $leave) }}" onsubmit="return confirm('Approve this leave request?')">
                    @csrf
                    <button type="submit" class="btn-primary"><i class="fas fa-check mr-2"></i>Approve Leave</button>
                </form>
                <form method="POST" action="{{ route('client.leaves.reject', $leave) }}" onsubmit="return confirm('Reject this leave request?')">
                    @csrf
                    <button type="submit" class="btn-danger"><i class="fas fa-times mr-2"></i>Reject Leave</button>
                </form>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
