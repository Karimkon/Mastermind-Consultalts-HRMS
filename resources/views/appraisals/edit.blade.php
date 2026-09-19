@extends('layouts.app')
@section('title','Set KPIs')
@section('content')

@php
    $total     = $appraisal->totalWeight();
    $remaining = $appraisal->remainingWeight();
    $complete  = $appraisal->weightIsComplete();
@endphp

@if($appraisal->status !== 'draft')
<div class="mb-4 flex items-start gap-3 px-4 py-3 bg-amber-50 border border-amber-300 rounded-lg text-amber-800 text-sm">
    <i class="fas fa-triangle-exclamation mt-0.5"></i>
    <div>
        <p class="font-semibold">This appraisal has already been sent out.</p>
        <p>You are editing it as an administrator. Changing a weight re-scores the card, and the change is recorded in its history.</p>
    </div>
</div>
@endif

<x-page-header title="Set KPIs" subtitle="{{ $appraisal->employee?->full_name }} — {{ $appraisal->title }}">
    <a href="{{ route('appraisals.show', $appraisal) }}" class="btn-secondary">
        <i class="fas fa-eye mr-1"></i> Preview Scorecard
    </a>
</x-page-header>

@foreach(['success' => ['green','check-circle'], 'error' => ['red','circle-exclamation']] as $key => [$c,$icon])
    @if(session($key))
    <div class="mb-4 flex items-center gap-3 px-4 py-3 bg-{{ $c }}-50 border border-{{ $c }}-200 rounded-lg text-{{ $c }}-700 text-sm">
        <i class="fas fa-{{ $icon }}"></i> {{ session($key) }}
    </div>
    @endif
@endforeach

{{-- The weight meter. The card must come to exactly 100% before it can go out. --}}
<div class="card p-5 mb-5">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <div>
            <p class="font-semibold text-slate-800">Total weighting</p>
            <p class="text-xs text-slate-500">Every KPI's weight, added up. It must reach exactly 100%.</p>
            @if($appraisal->template)
            <p class="text-xs text-slate-400 mt-0.5">
                Template: <strong>{{ $appraisal->template->name }}</strong> —
                target split {{ rtrim(rtrim(number_format($appraisal->template->financial_weight,2),'0'),'.') }}
                / {{ rtrim(rtrim(number_format($appraisal->template->customer_weight,2),'0'),'.') }}
                / {{ rtrim(rtrim(number_format($appraisal->template->internal_process_weight,2),'0'),'.') }}
                / {{ rtrim(rtrim(number_format($appraisal->template->learning_growth_weight,2),'0'),'.') }}
            </p>
            @endif
        </div>
        <p class="text-3xl font-bold {{ $complete ? 'text-emerald-600' : ($total > 100 ? 'text-rose-600' : 'text-amber-600') }}">
            {{ rtrim(rtrim(number_format($total, 2), '0'), '.') }}%
        </p>
    </div>
    <div class="w-full h-3 bg-slate-100 rounded-full overflow-hidden">
        <div class="h-full rounded-full transition-all
                    {{ $complete ? 'bg-emerald-500' : ($total > 100 ? 'bg-rose-500' : 'bg-amber-500') }}"
             style="width: {{ min(100, $total) }}%"></div>
    </div>
    <p class="text-xs mt-2 {{ $complete ? 'text-emerald-600' : 'text-amber-600' }}">
        @if($complete)
            <i class="fas fa-check-circle mr-1"></i> Fully allocated — this card is ready to send.
        @else
            <i class="fas fa-circle-info mr-1"></i> {{ rtrim(rtrim(number_format($remaining, 2), '0'), '.') }}% still to allocate.
        @endif
    </p>
</div>

{{-- Existing KPIs, grouped as they print --}}
@foreach($appraisal->kpisByPerspective() as $key => $group)
<div class="card mb-4 overflow-hidden">
    <div class="px-5 py-3 bg-yellow-200 flex items-center justify-between">
        <p class="font-bold text-slate-900 text-sm">
            {{ $loop->iteration }}. {{ strtoupper($group['label']) }}
        </p>
        <p class="font-bold text-slate-900 text-sm">
            {{ rtrim(rtrim(number_format($group['weight'], 2), '0'), '.') }}%
            @if($group['target'] !== null)
                @php $onTarget = abs($group['weight'] - $group['target']) < 0.01; @endphp
                <span class="ml-1 font-normal text-xs {{ $onTarget ? 'text-emerald-800' : 'text-rose-700' }}">
                    / {{ rtrim(rtrim(number_format($group['target'], 2), '0'), '.') }}% target
                    @unless($onTarget)<i class="fas fa-triangle-exclamation"></i>@endunless
                </span>
            @endif
        </p>
    </div>

    @forelse($group['rows'] as $kpi)
    <form method="POST" action="{{ route('appraisals.kpis.update', [$appraisal, $kpi]) }}"
          class="p-4 border-b border-slate-100 grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
        @csrf @method('PUT')
        <div class="md:col-span-3">
            <label class="text-xs text-slate-500">Key result area</label>
            <input type="text" name="kra_name" class="form-input text-sm" value="{{ $kpi->kra_name }}" required>
        </div>
        <div class="md:col-span-4">
            <label class="text-xs text-slate-500">Performance measure</label>
            <input type="text" name="performance_measure" class="form-input text-sm" value="{{ $kpi->performance_measure }}">
        </div>
        <div class="md:col-span-1">
            <label class="text-xs text-slate-500">Target</label>
            <input type="text" name="target" class="form-input text-sm" value="{{ $kpi->target }}" placeholder="100%">
        </div>
        <div class="md:col-span-1">
            <label class="text-xs text-slate-500">Weight %</label>
            <input type="number" step="0.01" min="0.01" max="100" name="weightage"
                   class="form-input text-sm" value="{{ rtrim(rtrim(number_format($kpi->weightage, 2), '0'), '.') }}" required>
        </div>
        <div class="md:col-span-2">
            <label class="text-xs text-slate-500">Evidence expected</label>
            <input type="text" name="evidence_note" class="form-input text-sm"
                   value="{{ $kpi->evidence_note }}" placeholder="e.g. Survey forms">
        </div>
        <div class="md:col-span-1 flex gap-1 pt-5">
            <button class="btn-secondary px-2 py-1.5" title="Save"><i class="fas fa-save text-xs"></i></button>
        </div>
    </form>
    @empty
    <p class="px-5 py-3 text-sm text-slate-400">No KPIs under this perspective yet.</p>
    @endforelse

    @foreach($group['rows'] as $kpi)
    <form method="POST" action="{{ route('appraisals.kpis.destroy', [$appraisal, $kpi]) }}"
          id="del-{{ $kpi->id }}" class="hidden">@csrf @method('DELETE')</form>
    @endforeach
</div>
@endforeach

{{-- Add a KPI --}}
@if($remaining > 0)
<div class="card p-5 mb-5">
    <h3 class="font-semibold text-slate-800 mb-3">Add a KPI</h3>
    <form method="POST" action="{{ route('appraisals.kpis.store', $appraisal) }}"
          class="grid grid-cols-1 md:grid-cols-12 gap-3">
        @csrf
        <div class="md:col-span-3">
            <label class="text-xs text-slate-500">Perspective</label>
            <select name="perspective" class="form-select text-sm" required>
                @foreach(\App\Models\Appraisal::PERSPECTIVES as $k => $label)
                    <option value="{{ $k }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-3">
            <label class="text-xs text-slate-500">Key result area</label>
            <input type="text" name="kra_name" class="form-input text-sm" required placeholder="e.g. Customer Retention">
        </div>
        <div class="md:col-span-3">
            <label class="text-xs text-slate-500">Performance measure</label>
            <input type="text" name="performance_measure" class="form-input text-sm"
                   placeholder="e.g. Retain all existing customers">
        </div>
        <div class="md:col-span-1">
            <label class="text-xs text-slate-500">Target</label>
            <input type="text" name="target" class="form-input text-sm" placeholder="100%">
        </div>
        <div class="md:col-span-1">
            <label class="text-xs text-slate-500">Weight %</label>
            {{-- Capped at what is left, so 100% cannot be exceeded from the form --}}
            <input type="number" step="0.01" min="0.01" max="{{ $remaining }}" name="weightage"
                   class="form-input text-sm" required placeholder="{{ $remaining }}">
        </div>
        <div class="md:col-span-1 pt-5">
            <button class="btn-primary w-full px-2"><i class="fas fa-plus"></i></button>
        </div>
        <div class="md:col-span-12">
            <input type="text" name="evidence_note" class="form-input text-sm"
                   placeholder="Evidence expected for this KPI — e.g. customer survey results, minutes, monthly reports">
        </div>
    </form>
    <p class="text-xs text-slate-500 mt-2">
        At most <strong>{{ rtrim(rtrim(number_format($remaining, 2), '0'), '.') }}%</strong> can be given to the next KPI.
    </p>
</div>
@else
<div class="mb-5 p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-800">
    <i class="fas fa-check-circle mr-1"></i>
    The full 100% is allocated. Remove or reduce a KPI's weight if you need room for another.
</div>
@endif

{{-- Send it out --}}
<div class="card p-5">
    <h3 class="font-semibold text-slate-800 mb-1">Send for appraisal</h3>
    <p class="text-xs text-slate-500 mb-3">
        The appraiser is notified, fills in the actuals and ratings, then returns it to you —
        or to someone else they nominate.
    </p>
    <form method="POST" action="{{ route('appraisals.send', $appraisal) }}"
          class="grid grid-cols-1 md:grid-cols-3 gap-3">
        @csrf
        <select name="appraiser_id" class="form-select select2" required>
            <option value="">Choose the appraiser…</option>
            @foreach($appraisers as $u)
                <option value="{{ $u->id }}" @selected($appraisal->appraiser_id == $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
        <input type="text" name="comment" class="form-input" placeholder="Note for the appraiser (optional)">
        <button class="btn-primary {{ $complete ? '' : 'opacity-60' }}">
            <i class="fas fa-paper-plane mr-1"></i> Send for Appraisal
        </button>
    </form>
    @unless($complete)
    <p class="text-xs text-amber-600 mt-2">
        <i class="fas fa-triangle-exclamation mr-1"></i>
        The weights come to {{ rtrim(rtrim(number_format($total, 2), '0'), '.') }}%. They must total exactly 100% before this can be sent.
    </p>
    @endunless
</div>
@endsection
