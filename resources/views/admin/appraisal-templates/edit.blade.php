@extends('layouts.app')
@section('title','Edit Template')
@section('content')

@php
    $kpiWeight = $template->kpiWeight();
    $remaining = round(100 - $kpiWeight, 2);
    $grouped   = $template->kpis->groupBy('perspective');
    $targets   = $template->perspectiveWeights();
@endphp

<x-page-header title="{{ $template->name }}" subtitle="Perspective weighting and starting KPIs">
    {{-- Reading last cycle's template is exactly when you decide to base the
         next one on it, so the copy is offered here and not only on the list. --}}
    <form method="POST" action="{{ route('admin.appraisal-templates.duplicate', $template) }}" class="inline">
        @csrf
        <button class="btn-secondary" title="Copy this template and its {{ $template->kpis->count() }} KPI(s)">
            <i class="fas fa-copy mr-1"></i> Duplicate
        </button>
    </form>
    <a href="{{ route('admin.appraisal-templates.index') }}" class="btn-secondary">
        <i class="fas fa-arrow-left mr-1"></i> Back
    </a>
</x-page-header>

@if(! $template->is_active)
    {{-- A fresh copy lands here switched off. Without saying so, the first sign
         is a supervisor reporting the template missing from the picker. --}}
    <div class="mb-4 flex items-center gap-3 px-4 py-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-sm">
        <i class="fas fa-eye-slash"></i>
        This template is inactive, so supervisors cannot apply it yet. Tick <strong>Active</strong> below and save when it is ready.
    </div>
@endif

@foreach(['success' => ['green','check-circle'], 'error' => ['red','circle-exclamation']] as $key => [$c,$icon])
    @if(session($key))
    <div class="mb-4 flex items-center gap-3 px-4 py-3 bg-{{ $c }}-50 border border-{{ $c }}-200 rounded-lg text-{{ $c }}-700 text-sm">
        <i class="fas fa-{{ $icon }}"></i> {{ session($key) }}
    </div>
    @endif
@endforeach

{{-- Perspective policy --}}
<form method="POST" action="{{ route('admin.appraisal-templates.update', $template) }}" class="card p-5 mb-5">
@csrf @method('PUT')
<h3 class="font-semibold text-slate-800 mb-1">Perspective weighting</h3>
<p class="text-xs text-slate-500 mb-4">The share of the scorecard each perspective carries. Must total 100%.</p>

<div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
    <div><label class="form-label">Name</label>
        <input type="text" name="name" class="form-input" value="{{ $template->name }}" required></div>
    <div><label class="form-label">Suits job title</label>
        <input type="text" name="job_title" class="form-input" value="{{ $template->job_title }}"></div>
    <div><label class="form-label">Description</label>
        <input type="text" name="description" class="form-input" value="{{ $template->description }}"></div>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    @foreach([
        'financial_weight' => 'Financials',
        'customer_weight' => 'Customer Perspective',
        'internal_process_weight' => 'Internal Business Process',
        'learning_growth_weight' => 'Learning &amp; Growth',
    ] as $field => $label)
    <div>
        <label class="form-label">{!! $label !!}</label>
        <div class="relative">
            <input type="number" step="0.01" min="0" max="100" name="{{ $field }}"
                   class="form-input pr-7" value="{{ rtrim(rtrim(number_format($template->$field, 2), '0'), '.') }}" required>
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">%</span>
        </div>
    </div>
    @endforeach
</div>

<div class="flex flex-wrap items-center justify-between gap-3 mt-4 pt-4 border-t border-slate-100">
    <div>
        <p class="text-sm text-slate-600">Currently totals
            <strong class="{{ $template->weightIsComplete() ? 'text-emerald-600' : 'text-rose-600' }}">
                {{ rtrim(rtrim(number_format($template->totalWeight(), 2), '0'), '.') }}%
            </strong>
        </p>
        <label class="inline-flex items-center gap-2 text-sm mt-1">
            <input type="checkbox" name="is_active" value="1" @checked($template->is_active)> Active
        </label>
    </div>
    <button class="btn-primary"><i class="fas fa-save mr-1"></i> Save Weighting</button>
</div>
</form>

{{-- Starting KPIs --}}
<div class="card p-5 mb-5">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-1">
        <h3 class="font-semibold text-slate-800">Starting KPIs</h3>
        <p class="text-sm {{ abs($kpiWeight - 100) < 0.01 ? 'text-emerald-600' : 'text-amber-600' }}">
            {{ rtrim(rtrim(number_format($kpiWeight, 2), '0'), '.') }}% of 100% allocated
        </p>
    </div>
    <p class="text-xs text-slate-500 mb-4">
        Copied onto every appraisal that uses this template. Supervisors can then adjust them.
    </p>

    @foreach(\App\Models\Appraisal::PERSPECTIVES as $key => $label)
    @php
        $rows   = $grouped->get($key, collect());
        $actual = round((float) $rows->sum('weightage'), 2);
        $target = $targets[$key] ?? 0;
        $match  = abs($actual - $target) < 0.01;
    @endphp
    <div class="mb-4">
        <div class="flex items-center justify-between px-3 py-2 bg-yellow-200 rounded-t-lg">
            <p class="font-bold text-slate-900 text-sm">{{ $loop->iteration }}. {{ strtoupper($label) }}</p>
            <p class="text-xs font-semibold {{ $match ? 'text-emerald-800' : 'text-rose-700' }}">
                {{ rtrim(rtrim(number_format($actual, 2), '0'), '.') }}% allocated
                / {{ rtrim(rtrim(number_format($target, 2), '0'), '.') }}% target
                @unless($match)<i class="fas fa-triangle-exclamation ml-1"></i>@endunless
            </p>
        </div>
        <div class="border border-t-0 border-slate-200 rounded-b-lg divide-y divide-slate-100">
            @forelse($rows as $kpi)
            {{-- Two states in one row: a readable summary, and the same values in
                 inputs. Editing used to mean deleting and retyping, which also
                 pushed the KPI to the bottom of its perspective because
                 sort_order is assigned on create -- so correcting one target in a
                 card of nineteen reshuffled the card. --}}
            <div class="p-3" id="kpi-{{ $kpi->id }}">
                <div class="flex items-start justify-between gap-3" data-kpi-view>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-slate-800">{{ $kpi->kra_name }}</p>
                        <p class="text-xs text-slate-500">{{ $kpi->performance_measure }}</p>
                        @if($kpi->evidence_note)
                            <p class="text-xs text-slate-400 mt-0.5"><i class="fas fa-paperclip mr-1"></i>{{ $kpi->evidence_note }}</p>
                        @endif
                    </div>
                    <div class="text-right whitespace-nowrap">
                        <p class="text-xs text-slate-400">Target {{ $kpi->target ?: '—' }}</p>
                        <p class="text-sm font-semibold text-slate-700">
                            {{ rtrim(rtrim(number_format($kpi->weightage, 2), '0'), '.') }}%
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button" class="text-slate-400 hover:text-blue-600 text-xs"
                                title="Edit this KPI" onclick="toggleKpi({{ $kpi->id }})">
                            <i class="fas fa-pen"></i>
                        </button>
                        <form method="POST" action="{{ route('admin.appraisal-templates.kpis.destroy', [$template, $kpi]) }}"
                              onsubmit="return confirm('Remove this KPI from the template?')">
                            @csrf @method('DELETE')
                            <button class="text-rose-500 hover:text-rose-700 text-xs" title="Remove"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.appraisal-templates.kpis.update', [$template, $kpi]) }}"
                      class="hidden mt-1 space-y-2" data-kpi-edit>
                    @csrf @method('PUT')

                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-2">
                        {{-- Perspective is editable here too: a KRA filed under the
                             wrong one otherwise has to be deleted and rebuilt. --}}
                        <select name="perspective" class="form-input text-sm sm:col-span-3">
                            @foreach(\App\Models\Appraisal::PERSPECTIVES as $key => $label)
                                <option value="{{ $key }}" @selected($kpi->perspective === $key)>{{ $label }}</option>
                            @endforeach
                        </select>

                        <input type="text" name="kra_name" value="{{ $kpi->kra_name }}"
                               class="form-input text-sm sm:col-span-5" placeholder="Key result area" required>

                        <input type="text" name="target" value="{{ $kpi->target }}"
                               class="form-input text-sm sm:col-span-2" placeholder="Target">

                        <div class="relative sm:col-span-2">
                            <input type="number" name="weightage" step="0.01" min="0.01" max="100"
                                   value="{{ rtrim(rtrim(number_format($kpi->weightage, 2, '.', ''), '0'), '.') }}"
                                   class="form-input text-sm pr-7" required>
                            <span class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-slate-400">%</span>
                        </div>
                    </div>

                    <input type="text" name="performance_measure" value="{{ $kpi->performance_measure }}"
                           class="form-input text-sm" placeholder="Performance measure">

                    <input type="text" name="evidence_note" value="{{ $kpi->evidence_note }}"
                           class="form-input text-sm" placeholder="Evidence expected">

                    <div class="flex items-center gap-2">
                        <button class="btn-primary text-xs">Save changes</button>
                        <button type="button" class="text-xs text-slate-500 hover:text-slate-700"
                                onclick="toggleKpi({{ $kpi->id }})">Cancel</button>
                    </div>
                </form>
            </div>
            @empty
            <p class="p-3 text-xs text-slate-400">No KPIs under this perspective yet.</p>
            @endforelse
        </div>
    </div>
    @endforeach

    @if($remaining > 0)
    <form method="POST" action="{{ route('admin.appraisal-templates.kpis.store', $template) }}"
          class="grid grid-cols-1 md:grid-cols-12 gap-3 pt-4 border-t border-slate-100">
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
            <input type="text" name="kra_name" class="form-input text-sm" required>
        </div>
        <div class="md:col-span-3">
            <label class="text-xs text-slate-500">Performance measure</label>
            <input type="text" name="performance_measure" class="form-input text-sm">
        </div>
        <div class="md:col-span-1">
            <label class="text-xs text-slate-500">Target</label>
            <input type="text" name="target" class="form-input text-sm" placeholder="100%">
        </div>
        <div class="md:col-span-1">
            <label class="text-xs text-slate-500">Weight %</label>
            <input type="number" step="0.01" min="0.01" max="{{ $remaining }}" name="weightage"
                   class="form-input text-sm" required>
        </div>
        <div class="md:col-span-1 pt-5">
            <button class="btn-primary w-full px-2"><i class="fas fa-plus"></i></button>
        </div>
        <div class="md:col-span-12">
            <input type="text" name="evidence_note" class="form-input text-sm"
                   placeholder="Evidence expected — e.g. customer survey results, minutes, monthly reports">
        </div>
    </form>
    <p class="text-xs text-slate-500 mt-2">
        At most <strong>{{ rtrim(rtrim(number_format($remaining, 2), '0'), '.') }}%</strong> left to allocate.
    </p>
    @else
    <p class="text-sm text-emerald-700 pt-4 border-t border-slate-100">
        <i class="fas fa-check-circle mr-1"></i> The full 100% is allocated across the KPIs.
    </p>
    @endif
</div>
@endsection

@push('scripts')
<script>
    // Swap one row between its summary and its form. Nothing is fetched: the
    // inputs are already rendered carrying the current values, so an edit opens
    // instantly and cancelling costs nothing.
    function toggleKpi(id) {
        const row = document.getElementById('kpi-' + id);
        if (!row) return;

        row.querySelector('[data-kpi-view]').classList.toggle('hidden');

        const form = row.querySelector('[data-kpi-edit]');
        form.classList.toggle('hidden');

        if (!form.classList.contains('hidden')) {
            form.querySelector('input[name="kra_name"]').focus();
        }
    }
</script>
@endpush
