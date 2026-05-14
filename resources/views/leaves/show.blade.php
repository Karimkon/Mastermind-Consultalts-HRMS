@extends("layouts.app")
@section("title","Leave Request")
@section("content")
<x-page-header title="Leave Request Details">
    <a href="{{ route('leaves.index') }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</x-page-header>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ── Main card ──────────────────────────────────────────────── --}}
    <div class="lg:col-span-2 space-y-4">

        {{-- Employee + Leave summary --}}
        <div class="card p-6">
            <div class="flex items-start justify-between mb-6">
                <div class="flex items-center gap-4">
                    <img src="{{ $leave->employee?->avatar_url ?? 'https://ui-avatars.com/api/?name=?&background=94a3b8&color=fff&size=128' }}"
                         class="w-14 h-14 rounded-xl object-cover border-2 border-slate-200">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">{{ $leave->employee?->full_name ?? 'Unknown Employee' }}</h3>
                        <p class="text-sm text-slate-500">{{ $leave->employee?->designation?->title ?? '' }}</p>
                        <p class="text-xs text-slate-400">{{ $leave->employee?->department?->name ?? '' }}</p>
                    </div>
                </div>
                {!! $leave->status_badge !!}
            </div>

            {{-- Client / Company --}}
            @if($client)
            <div class="mb-5 flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-xl p-3">
                <div class="w-9 h-9 rounded-lg bg-blue-600 flex items-center justify-center shrink-0">
                    <i class="fas fa-building text-white text-sm"></i>
                </div>
                <div>
                    <p class="text-xs text-blue-600 font-semibold uppercase tracking-wider">Assigned Company (Leave Approver)</p>
                    <p class="text-sm font-bold text-blue-900">{{ $client->company_name }}</p>
                    @if($client->contact_person)
                    <p class="text-xs text-blue-600">Contact: {{ $client->contact_person }}</p>
                    @endif
                </div>
            </div>
            @endif

            {{-- Details grid --}}
            <div class="grid grid-cols-2 gap-4 mb-6">
                @php
                    $details = [
                        'Leave Type'     => $leave->leaveType?->name ?? '—',
                        'From Date'      => $leave->from_date?->format('M d, Y') ?? '—',
                        'To Date'        => $leave->to_date?->format('M d, Y') ?? '—',
                        'Days Requested' => ($leave->days_count ?? 0) . ' day(s)',
                        'Applied On'     => $leave->created_at?->format('M d, Y H:i') ?? '—',
                        'Approved By'    => $leave->approver?->name ?? '—',
                    ];
                @endphp
                @foreach($details as $label => $val)
                <div class="bg-slate-50 rounded-lg p-3">
                    <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">{{ $label }}</p>
                    <p class="text-sm font-semibold text-slate-800">{{ $val }}</p>
                </div>
                @endforeach
            </div>

            {{-- Reason --}}
            <div class="mb-4">
                <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Reason</p>
                <p class="text-sm text-slate-800 bg-slate-50 rounded-lg p-3">{{ $leave->reason }}</p>
            </div>

            {{-- Rejection reason --}}
            @if($leave->rejection_reason)
            <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                <p class="text-xs font-semibold text-red-600 uppercase mb-1">Rejection Reason</p>
                <p class="text-sm text-red-800">{{ $leave->rejection_reason }}</p>
            </div>
            @endif
        </div>

        {{-- Replacement / Cover Person --}}
        @if($leave->replacement_name)
        <div class="card p-5 border-l-4 border-amber-400">
            <p class="text-xs font-semibold text-amber-700 uppercase tracking-wide mb-3 flex items-center gap-2">
                <i class="fas fa-user-friends text-amber-500"></i> Cover / Replacement Person
            </p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-xs text-slate-400 uppercase tracking-wider mb-1">Full Name</p>
                    <p class="text-sm font-bold text-slate-800">{{ $leave->replacement_name }}</p>
                </div>
                @if($leave->replacement_email)
                <div>
                    <p class="text-xs text-slate-400 uppercase tracking-wider mb-1">Email</p>
                    <p class="text-sm text-slate-700">{{ $leave->replacement_email }}</p>
                </div>
                @endif
                @if($leave->replacement_phone)
                <div>
                    <p class="text-xs text-slate-400 uppercase tracking-wider mb-1">Phone</p>
                    <p class="text-sm text-slate-700">{{ $leave->replacement_phone }}</p>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Client Approval Status --}}
        @if($leave->client_approval_required)
        <div class="card p-4 bg-blue-50 border border-blue-200">
            <p class="text-xs font-semibold text-blue-700 uppercase mb-2 flex items-center gap-2">
                <i class="fas fa-building"></i> Client Approval Status
            </p>
            @if($leave->client_approval_status === 'approved')
                <span class="badge-green"><i class="fas fa-check mr-1"></i>Client Approved</span>
            @elseif($leave->client_approval_status === 'rejected')
                <span class="badge-red"><i class="fas fa-times mr-1"></i>Client Rejected</span>
            @else
                <span class="badge-yellow">Awaiting client decision</span>
            @endif
            @if($leave->clientApprover ?? null)
            <p class="text-xs text-blue-500 mt-1">by {{ $leave->clientApprover->company_name }}</p>
            @endif
        </div>
        @endif

    </div>

    {{-- ── Actions sidebar ────────────────────────────────────────── --}}
    <div class="space-y-4">

        {{-- Approve / Reject --}}
        @if($leave->status === 'pending')
        @can("leave.approve")
        <div class="card p-5">
            <h3 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2">
                <i class="fas fa-gavel text-blue-500"></i> Actions
            </h3>
            <form method="POST" action="{{ route('leaves.approve', $leave) }}" class="mb-3">@csrf
                <button class="btn-primary w-full justify-center">
                    <i class="fas fa-check mr-1"></i> Approve Leave
                </button>
            </form>
            <form method="POST" action="{{ route('leaves.reject', $leave) }}">@csrf
                <textarea name="rejection_reason" class="form-input mb-2" rows="2"
                          placeholder="Reason for rejection..." required></textarea>
                <button class="btn-danger w-full justify-center">
                    <i class="fas fa-times mr-1"></i> Reject Leave
                </button>
            </form>
        </div>
        @endcan
        @endif

        {{-- Cancel --}}
        @if(in_array($leave->status, ['pending', 'approved']))
        <form method="POST" action="{{ route('leaves.cancel', $leave) }}"
              onsubmit="return confirm('Cancel this leave request?')">@csrf
            <button class="btn-secondary w-full justify-center">
                <i class="fas fa-ban mr-1"></i> Cancel Request
            </button>
        </form>
        @endif

        {{-- Info panel --}}
        <div class="card p-4 bg-slate-50 text-xs text-slate-600 space-y-1">
            <p class="font-semibold text-slate-700 mb-2 flex items-center gap-1">
                <i class="fas fa-info-circle text-blue-500"></i> Approval Flow
            </p>
            <p class="{{ $leave->status !== 'pending' ? 'line-through text-slate-400' : '' }}">1. Employee submits with replacement details</p>
            <p>2. Email sent to Account Manager + Client</p>
            <p class="{{ $leave->status === 'approved' ? 'text-green-600 font-semibold' : ($leave->status === 'rejected' ? 'text-red-500 line-through' : '') }}">
                3. Account Manager / Client approves or rejects
            </p>
            <p>4. Employee notified by email</p>
        </div>

    </div>
</div>
@endsection
