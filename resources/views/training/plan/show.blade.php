@extends('layouts.app')
@section('title', $session->title)

@section('content')
<div class="flex items-start justify-between flex-wrap gap-3 mb-4">
    <div>
        <a href="{{ route('training.plan.index', ['year' => $session->plan_year]) }}"
           class="text-sm text-blue-600 hover:underline">
            <i class="fas fa-arrow-left"></i> Training Plan {{ $session->plan_year }}
        </a>
        <h1 class="text-xl font-bold text-slate-800 mt-1">{{ $session->title }}</h1>
        <p class="text-sm text-slate-500">
            {{ $session->category ?: 'Uncategorised' }} ·
            {{ ucwords(str_replace('_', ' ', $session->delivery)) }}
        </p>
    </div>
    <div class="flex items-center gap-2">
        <span class="badge-{{ $session->statusBadge() }}">{{ $session->statusLabel() }}</span>
        @if($canEdit)
            <a href="{{ route('training.plan.edit', $session) }}" class="btn-secondary text-sm">
                <i class="fas fa-pen mr-1"></i> Edit
            </a>
        @endif
    </div>
</div>

@include('admin.blog.partials.flash')

{{-- Where it is in the chain, and who has signed. A request in flight should say
     what it is waiting for rather than leaving people to guess. --}}
<div class="card p-4 mb-4">
    <div class="flex flex-wrap items-center gap-2 text-sm">
        @foreach([
            ['Raised',   $session->initiator?->name, $session->created_at],
            ['HR',       $session->hrApprover?->name, $session->hr_approved_at],
            ['CEO',      $session->ceoApprover?->name, $session->ceo_approved_at],
        ] as $i => [$stage, $who, $when])
            @if($i > 0)<i class="fas fa-chevron-right text-[10px] text-slate-300"></i>@endif
            <div class="px-2.5 py-1.5 rounded-lg border
                        {{ $who ? 'bg-emerald-50 border-emerald-200' : 'bg-slate-50 border-slate-200' }}">
                <p class="text-[11px] uppercase tracking-wide {{ $who ? 'text-emerald-700' : 'text-slate-400' }}">{{ $stage }}</p>
                <p class="text-xs {{ $who ? 'text-emerald-800 font-medium' : 'text-slate-400' }}">
                    {{ $who ?? 'Not yet' }}
                </p>
                @if($when)<p class="text-[10px] text-slate-400">{{ $when->format('d M Y H:i') }}</p>@endif
            </div>
        @endforeach
    </div>

    @if($session->status === 'rejected' && $session->decision_note)
        <p class="mt-3 text-sm text-rose-700 bg-rose-50 border border-rose-200 rounded-lg px-3 py-2">
            <strong>Sent back:</strong> {{ $session->decision_note }}
        </p>
    @endif

    <div class="flex flex-wrap items-center gap-2 mt-3">
        @if($session->isEditable() && ($session->initiated_by === auth()->id() || auth()->user()->hasAnyRole(['hr-admin','super-admin'])))
            <form method="POST" action="{{ route('training.plan.submit', $session) }}">
                @csrf
                <button class="btn-primary text-sm" data-loading-label="Submitting…">
                    <i class="fas fa-paper-plane mr-1"></i> Submit for approval
                </button>
            </form>
        @endif

        @if($canApprove)
            <form method="POST" action="{{ route('training.plan.approve', $session) }}">
                @csrf
                <button class="btn-primary text-sm" data-loading-label="Approving…">
                    <i class="fas fa-check mr-1"></i>
                    {{ $canApprove === 'hr' ? 'Approve and send to CEO' : 'Approve — commit to the plan' }}
                </button>
            </form>

            <form method="POST" action="{{ route('training.plan.reject', $session) }}"
                  class="flex items-center gap-2">
                @csrf
                <input type="text" name="decision_note" class="form-input text-sm" required
                       placeholder="Why it is going back">
                <button class="btn-secondary text-sm text-rose-600" data-loading-label="Sending back…">
                    <i class="fas fa-undo mr-1"></i> Send back
                </button>
            </form>
        @endif

        @if($session->status === 'approved' && auth()->user()->hasAnyRole(['hr-admin','super-admin']))
            <form method="POST" action="{{ route('training.plan.complete', $session) }}">
                @csrf
                <button class="btn-secondary text-sm" data-loading-label="Closing…">
                    <i class="fas fa-flag-checkered mr-1"></i> Mark complete
                </button>
            </form>
        @endif
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
    <div class="card p-4 lg:col-span-2">
        <h2 class="font-semibold text-slate-800 mb-2">Detail</h2>
        <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
            <div><dt class="text-slate-400 text-xs">Dates</dt>
                <dd class="text-slate-700">
                    {{ $session->starts_on?->format('d M Y') ?? '—' }}
                    @if($session->ends_on && $session->starts_on && !$session->ends_on->isSameDay($session->starts_on))
                        – {{ $session->ends_on->format('d M Y') }}
                    @endif
                </dd></div>
            <div><dt class="text-slate-400 text-xs">Duration</dt>
                <dd class="text-slate-700">
                    {{ $session->duration_days ? rtrim(rtrim(number_format($session->duration_days,1),'0'),'.') . ' day(s)' : '' }}
                    {{ $session->duration_hours ? rtrim(rtrim(number_format($session->duration_hours,1),'0'),'.') . ' hour(s)' : '' }}
                    {{ !$session->duration_days && !$session->duration_hours ? '—' : '' }}
                </dd></div>
            <div><dt class="text-slate-400 text-xs">Trainer</dt><dd class="text-slate-700">{{ $session->trainer ?: '—' }}</dd></div>
            <div><dt class="text-slate-400 text-xs">Provider</dt><dd class="text-slate-700">{{ $session->provider ?: '—' }}</dd></div>
            <div><dt class="text-slate-400 text-xs">Venue</dt><dd class="text-slate-700">{{ $session->venue ?: '—' }}</dd></div>
            <div><dt class="text-slate-400 text-xs">Seats</dt>
                <dd class="text-slate-700">{{ $session->participants->count() }}@if($session->max_participants) of {{ $session->max_participants }}@endif</dd></div>
        </dl>

        @if(filled($session->justification))
            <p class="text-xs text-slate-400 mt-3">Why it is needed</p>
            <p class="text-sm text-slate-700 whitespace-pre-line">{{ $session->justification }}</p>
        @endif
    </div>

    <div class="card p-4">
        <h2 class="font-semibold text-slate-800 mb-2">Cost</h2>
        <dl class="text-sm space-y-1">
            @foreach([
                ['Pedagogic', $session->cost_pedagogic],
                ['Logistic', $session->cost_logistic],
                ['Remuneration', $session->cost_remuneration],
                ['Company logistic', $session->cost_company],
            ] as [$label, $value])
            <div class="flex justify-between">
                <dt class="text-slate-500">{{ $label }}</dt>
                <dd class="text-slate-700">{{ number_format($value, 2) }}</dd>
            </div>
            @endforeach
            <div class="flex justify-between border-t border-slate-200 pt-1 font-semibold">
                <dt class="text-slate-700">Total</dt>
                <dd class="text-slate-900">{{ number_format($session->totalCost(), 2) }}</dd>
            </div>
            @if($session->costPerParticipant() !== null)
            <div class="flex justify-between text-xs">
                <dt class="text-slate-400">Per person</dt>
                <dd class="text-slate-500">{{ number_format($session->costPerParticipant(), 2) }}</dd>
            </div>
            @endif
        </dl>
    </div>
</div>

@include('training.plan.partials.participants', ['session' => $session, 'employees' => $employees])
@endsection
