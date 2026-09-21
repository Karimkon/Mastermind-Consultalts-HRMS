@extends("layouts.app")
@section("title","Leave Request")
@section("content")
<x-page-header title="Leave Request Details">
    <a href="{{ route('leaves.index') }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</x-page-header>

@foreach(['success' => ['green','check-circle'], 'error' => ['red','circle-exclamation']] as $key => [$c,$icon])
    @if(session($key))
    <div class="mb-4 flex items-center gap-3 px-4 py-3 bg-{{ $c }}-50 border border-{{ $c }}-200 rounded-lg text-{{ $c }}-700 text-sm">
        <i class="fas fa-{{ $icon }}"></i> {{ session($key) }}
    </div>
    @endif
@endforeach

@if($errors->any())
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
    <i class="fas fa-circle-exclamation mr-1"></i> {{ $errors->first() }}
</div>
@endif

@if($balance)
{{-- What is left of the entitlement, so a decision to extend is made against
     the balance rather than in the dark. --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
    @foreach([
        ['Entitled', $balance['entitled'], 'slate'],
        ['Taken', $balance['used'], 'slate'],
        ['Reserved', $balance['pending'], 'amber'],
        ['Remaining', $balance['remaining'], $balance['remaining'] > 0 ? 'green' : 'rose'],
    ] as [$label, $value, $colour])
    <div class="rounded-xl border border-{{ $colour }}-200 bg-{{ $colour }}-50 p-4">
        <p class="text-2xl font-bold text-{{ $colour }}-700">{{ rtrim(rtrim(number_format($value, 2), '0'), '.') }}</p>
        <p class="text-xs text-slate-600">{{ $label }} day(s)</p>
    </div>
    @endforeach
</div>
@if(! $balance['seeded'])
<p class="-mt-3 mb-5 text-xs text-amber-700">
    <i class="fas fa-triangle-exclamation mr-1"></i>
    No balance has been opened for this person this year, so the entitlement shown is the
    {{ $leave->leaveType?->name ?? 'leave type' }} default. It is created the first time days move.
</p>
@endif
@endif

@if($leave->wasAdjusted() || $leave->recalled_at)
<div class="mb-5 flex items-start gap-3 px-4 py-3 bg-blue-50 border border-blue-200 rounded-lg text-blue-800 text-sm">
    <i class="fas fa-clock-rotate-left mt-0.5"></i>
    <div>
        @if($leave->recalled_at)
            <strong>Recalled
            @if($leave->recaller) by {{ $leave->recaller->name }} @endif
            on {{ $leave->recalled_at->format('d M Y') }}.</strong>
        @else
            <strong>This leave was changed after it was granted.</strong>
        @endif
        @if($leave->original_to_date)
        <span class="block text-xs mt-1">
            Originally ran to {{ $leave->original_to_date->format('d M Y') }}
            ({{ rtrim(rtrim(number_format((float) $leave->original_days, 2), '0'), '.') }} day(s));
            now {{ $leave->to_date?->format('d M Y') }}
            ({{ rtrim(rtrim(number_format((float) $leave->days_count, 2), '0'), '.') }} day(s)).
        </span>
        @endif
        @if($leave->adjustment_note)
        <span class="block text-xs mt-1 italic">&ldquo;{{ $leave->adjustment_note }}&rdquo;</span>
        @endif
    </div>
</div>
@endif

@if($canManage && in_array($leave->status, ['approved', 'pending'], true))
<div class="card p-5 mb-5 border-l-4 border-blue-500">
    <h3 class="font-semibold text-slate-800 mb-1">Manage this leave</h3>
    <p class="text-xs text-slate-500 mb-4">
        The days are recounted automatically and the balance moves by the difference only.
    </p>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {{-- Changing the end date covers both directions: a later date extends,
             an earlier one shortens. One control, no arithmetic to get wrong. --}}
        <form method="POST" action="{{ route('leaves.adjust', $leave) }}">
            @csrf
            <label class="form-label">Change the last day of leave</label>
            <div class="flex flex-wrap gap-2">
                <input type="date" name="to_date" class="form-input w-44"
                       value="{{ $leave->to_date?->toDateString() }}"
                       min="{{ $leave->from_date?->toDateString() }}" required>
                <input type="text" name="note" class="form-input flex-1 min-w-[10rem]" placeholder="Why (optional)">
                <button class="btn-primary"><i class="fas fa-calendar-day mr-1"></i> Apply</button>
            </div>
            <p class="text-xs text-slate-500 mt-2">
                A later date extends the leave, an earlier one shortens it. Currently
                {{ rtrim(rtrim(number_format((float) $leave->days_count, 2), '0'), '.') }} working day(s).
            </p>
        </form>

        @if($leave->status === 'approved')
        <form method="POST" action="{{ route('leaves.recall', $leave) }}"
              onsubmit="return confirm('Recall {{ $leave->employee?->full_name }} from leave? The unused days go back to their balance.')">
            @csrf
            <label class="form-label">Recall from leave</label>
            <div class="flex flex-wrap gap-2">
                <input type="text" name="note" class="form-input flex-1 min-w-[10rem]" placeholder="Reason (optional)">
                <button class="btn-secondary text-amber-700">
                    <i class="fas fa-person-walking-arrow-right mr-1"></i> Recall now
                </button>
            </div>
            <p class="text-xs text-slate-500 mt-2">
                @if($leave->from_date && $leave->from_date->isFuture())
                    This leave has not started, so recalling cancels it and returns all
                    {{ rtrim(rtrim(number_format((float) $leave->days_count, 2), '0'), '.') }} day(s).
                @else
                    The leave ends today and the remaining days go back to the balance.
                @endif
            </p>
        </form>
        @endif
    </div>
</div>
@endif

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
