@extends('layouts.app')
@section('title', 'Edit BSC Entry')
@section('content')
<x-page-header title="BSC Appraisal Entry">
    <a href="{{ route('bsc.my-appraisal') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> My Appraisal</a>
</x-page-header>

@if(session('success'))
<div class="alert-success mb-4">{{ session('success') }}</div>
@endif

{{-- KRA Info Header --}}
<div class="card p-4 mb-5 border-l-4 border-{{ ['financial'=>'blue','customer'=>'green','internal_process'=>'purple','learning_growth'=>'amber'][$entry->kra->perspective] ?? 'slate' }}-400">
    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">{{ $entry->kra->perspective_label }}</p>
            <h3 class="text-lg font-bold text-slate-900">{{ $entry->kra->kra_name }}</h3>
            @if($entry->kra->objective)<p class="text-sm text-slate-600">{{ $entry->kra->objective }}</p>@endif
            <div class="flex gap-4 mt-2 text-xs text-slate-500">
                <span><i class="fas fa-bullseye mr-1"></i>Target: <strong>{{ number_format($entry->kra->target, 0) }} {{ $entry->kra->unit }}</strong></span>
                <span><i class="fas fa-weight-hanging mr-1"></i>Weight: <strong>{{ $entry->kra->weightage }}%</strong></span>
            </div>
        </div>
        <div class="text-right">
            {!! $entry->status_badge !!}
            <p class="text-xs text-slate-400 mt-1">{{ $entry->kra->cycle?->name }}</p>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('bsc.entries.update', $entry) }}" id="entryForm">
@csrf @method('PUT')
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

    {{-- PART I: Performance Data --}}
    <div class="card p-5">
        <h4 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-chart-line text-blue-400"></i> Part I — Performance Assessment
        </h4>

        <div class="mb-4">
            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Actual Achieved</label>
            <div class="flex items-center gap-2">
                <input type="number" name="actual_achieved" id="actualInput"
                       value="{{ old('actual_achieved', $entry->actual_achieved) }}"
                       class="form-input flex-1" step="0.01" min="0" placeholder="0.00"
                       oninput="calcMetrics()">
                <span class="text-slate-500 text-sm font-medium">{{ $entry->kra->unit }}</span>
            </div>
        </div>

        {{-- Auto-computed fields --}}
        <div class="grid grid-cols-2 gap-3 mb-4">
            <div class="bg-slate-50 rounded-lg p-3 text-center">
                <p class="text-xs text-slate-500 mb-1">% of Target</p>
                <p class="text-xl font-bold" id="targetPct">
                    {{ $entry->target_percent !== null ? number_format($entry->target_percent, 1) . '%' : '—' }}
                </p>
            </div>
            <div class="bg-slate-50 rounded-lg p-3 text-center">
                <p class="text-xs text-slate-500 mb-1">Weighted Index</p>
                <p class="text-xl font-bold text-blue-600" id="weightedIdx">
                    {{ $entry->weighted_index !== null ? number_format($entry->weighted_index, 4) : '—' }}
                </p>
            </div>
        </div>

        {{-- Rating --}}
        <div class="mb-4">
            <label class="block text-xs font-semibold text-slate-600 uppercase mb-2">Rating (1–5) <span class="text-red-500">*</span></label>
            <div class="flex gap-2">
                @foreach([1,2,3,4,5] as $r)
                @php
                $labels = [1=>'Unacceptable',2=>'Below Target',3=>'On Target',4=>'Above Target',5=>'Exceptional'];
                $colors = [1=>'bg-red-100 text-red-700 border-red-300',2=>'bg-orange-100 text-orange-700 border-orange-300',3=>'bg-amber-100 text-amber-700 border-amber-300',4=>'bg-blue-100 text-blue-700 border-blue-300',5=>'bg-green-100 text-green-700 border-green-300'];
                @endphp
                <label class="flex-1 text-center cursor-pointer">
                    <input type="radio" name="rating" value="{{ $r }}" class="sr-only rating-radio"
                           {{ old('rating', $entry->rating) == $r ? 'checked' : '' }}>
                    <span class="block border-2 rounded-lg py-2 text-sm font-bold transition-all rating-pill
                                 {{ old('rating', $entry->rating) == $r ? $colors[$r] . ' border-2' : 'bg-white text-slate-400 border-slate-200 hover:bg-slate-50' }}">
                        {{ $r }}<br><span class="text-xs font-normal">{{ $labels[$r] }}</span>
                    </span>
                </label>
                @endforeach
            </div>
        </div>

        {{-- Employee Comment --}}
        <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Employee Comment</label>
            <textarea name="employee_comment" class="form-input" rows="3" placeholder="Your comments on this KRA performance…">{{ old('employee_comment', $entry->employee_comment) }}</textarea>
        </div>
    </div>

    {{-- PART II: Problem Areas & Remedial --}}
    <div class="card p-5">
        <h4 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-exclamation-triangle text-amber-400"></i> Part II — Problem Areas & Remedial Actions
        </h4>

        <div class="mb-4">
            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Problem Areas Identified</label>
            <textarea name="problem_areas" class="form-input" rows="3" placeholder="Describe any challenges or obstacles faced…">{{ old('problem_areas', $entry->problem_areas) }}</textarea>
        </div>

        <div class="mb-4">
            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Remedial Actions</label>
            <textarea name="remedial_actions" class="form-input" rows="3" placeholder="Actions to be taken to address the problem areas…">{{ old('remedial_actions', $entry->remedial_actions) }}</textarea>
        </div>

        <div class="mb-4">
            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">By When (Target Completion Date)</label>
            <input type="date" name="remedial_by_when" class="form-input"
                   value="{{ old('remedial_by_when', $entry->remedial_by_when?->format('Y-m-d')) }}">
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Appraiser Comment</label>
            <textarea name="appraiser_comment" class="form-input" rows="3" placeholder="Manager / appraiser feedback on this entry…">{{ old('appraiser_comment', $entry->appraiser_comment) }}</textarea>
        </div>
    </div>
</div>

<div class="flex gap-3 mt-5">
    <button type="submit" name="action" value="save" class="btn-secondary">
        <i class="fas fa-save mr-1"></i> Save Draft
    </button>
    @if($entry->status === 'draft')
    <button type="submit" name="action" value="submit" class="btn-primary"
            onclick="document.getElementById('entryForm').action='{{ route('bsc.entries.submit', $entry) }}'">
        <i class="fas fa-paper-plane mr-1"></i> Save & Submit
    </button>
    @endif
    <a href="{{ route('bsc.my-appraisal') }}" class="btn-secondary">Cancel</a>
</div>
</form>

<script>
const target    = {{ $entry->kra->target ?? 0 }};
const weightage = {{ $entry->kra->weightage ?? 0 }};

document.querySelectorAll('.rating-radio').forEach(function(r) {
    r.addEventListener('change', function() {
        document.querySelectorAll('.rating-pill').forEach(function(p) {
            p.className = p.className.replace(/bg-\w+-100 text-\w+-700 border-\w+-300/g, '').trim();
            p.classList.add('bg-white', 'text-slate-400', 'border-slate-200');
        });
        const colors = {1:'bg-red-100 text-red-700 border-red-300',2:'bg-orange-100 text-orange-700 border-orange-300',3:'bg-amber-100 text-amber-700 border-amber-300',4:'bg-blue-100 text-blue-700 border-blue-300',5:'bg-green-100 text-green-700 border-green-300'};
        const pill = this.nextElementSibling;
        const v = parseInt(this.value);
        pill.classList.remove('bg-white','text-slate-400','border-slate-200','hover:bg-slate-50');
        pill.className += ' ' + colors[v];
        calcMetrics();
    });
});

function calcMetrics() {
    const actual = parseFloat(document.getElementById('actualInput').value);
    const rating = parseFloat(document.querySelector('.rating-radio:checked')?.value ?? 0);
    const pctEl  = document.getElementById('targetPct');
    const widxEl = document.getElementById('weightedIdx');

    if (!isNaN(actual) && target > 0) {
        pctEl.textContent = (actual / target * 100).toFixed(1) + '%';
    }
    if (rating > 0 && weightage > 0) {
        widxEl.textContent = (rating * weightage / 100).toFixed(4);
    }
}
</script>
@endsection
