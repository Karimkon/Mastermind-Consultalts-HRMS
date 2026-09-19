@extends('layouts.app')
@section('title','New Appraisal')
@section('content')

<x-page-header title="New Appraisal" subtitle="Pick the employee and the review period, then set their KPIs">
    <a href="{{ route('appraisals.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>

@if(session('error'))
<div class="mb-4 flex items-center gap-3 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
    <i class="fas fa-circle-exclamation"></i> {{ session('error') }}
</div>
@endif

<form method="POST" action="{{ route('appraisals.store') }}" class="card p-6 max-w-3xl" x-data="{ type: '{{ old('type','internal') }}' }">
@csrf
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <label class="form-label">Employee <span class="text-red-500">*</span></label>
        <select name="employee_id" class="form-select select2" required>
            <option value="">Choose an employee…</option>
            @foreach($employees as $e)
                <option value="{{ $e->id }}" @selected(old('employee_id')==$e->id)>
                    {{ $e->full_name }} — {{ $e->emp_number }}{{ $e->department ? ' · '.$e->department->name : '' }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="md:col-span-2">
        <label class="form-label">Title <span class="text-red-500">*</span></label>
        <input type="text" name="title" class="form-input" required
               value="{{ old('title', 'Quarterly Appraisal ' . now()->year) }}">
    </div>

    <div>
        <label class="form-label">Appraisal type <span class="text-red-500">*</span></label>
        <select name="type" x-model="type" class="form-select" required>
            <option value="internal">Internal — appraised by our own manager</option>
            <option value="external">External — appraised by the client</option>
        </select>
        <p class="text-xs text-slate-500 mt-1"
           x-text="type === 'external'
                ? 'The client scores the staff placed on their site.'
                : 'An account manager or line manager scores the employee.'"></p>
    </div>

    <div>
        <label class="form-label">Client <span x-show="type === 'external'" class="text-red-500">*</span></label>
        <select name="client_id" class="form-select select2">
            <option value="">— none —</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" @selected(old('client_id')==$c->id)>{{ $c->company_name }}</option>
            @endforeach
        </select>
        <p class="text-xs text-slate-500 mt-1" x-show="type === 'external'" x-cloak>
            Required for an external appraisal.
        </p>
    </div>

    <div>
        <label class="form-label">Year <span class="text-red-500">*</span></label>
        <select name="year" class="form-select" required>
            @for($y = now()->year - 1; $y <= now()->year + 1; $y++)
                <option value="{{ $y }}" @selected(old('year', now()->year)==$y)>{{ $y }}</option>
            @endfor
        </select>
    </div>

    <div>
        <label class="form-label">Period</label>
        <select name="period" class="form-select">
            @foreach(['Q1','Q2','Q3','Q4','Annual'] as $p)
                <option value="{{ $p }}" @selected(old('period')===$p)>{{ $p }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="form-label">Review period from</label>
        <input type="date" name="review_from" class="form-input" value="{{ old('review_from') }}">
    </div>
    <div>
        <label class="form-label">Review period to</label>
        <input type="date" name="review_to" class="form-input" value="{{ old('review_to') }}">
    </div>

    <div class="md:col-span-2">
        <label class="form-label">Weighting template</label>
        <select name="appraisal_template_id" class="form-select select2">
            <option value="">None — I'll set the KPIs myself</option>
            @foreach($templates as $t)
                <option value="{{ $t->id }}" @selected(old('appraisal_template_id')==$t->id)>
                    {{ $t->name }}{{ $t->job_title ? ' — '.$t->job_title : '' }}
                    ({{ rtrim(rtrim(number_format($t->financial_weight,2),'0'),'.') }}/{{ rtrim(rtrim(number_format($t->customer_weight,2),'0'),'.') }}/{{ rtrim(rtrim(number_format($t->internal_process_weight,2),'0'),'.') }}/{{ rtrim(rtrim(number_format($t->learning_growth_weight,2),'0'),'.') }})
                </option>
            @endforeach
        </select>
        <p class="text-xs text-slate-500 mt-1">
            Copies the admin's KPI set and weighting in as a starting point. You can change any of it afterwards.
        </p>
    </div>

    <div class="md:col-span-2">
        <label class="form-label">Send to (appraiser)</label>
        <select name="appraiser_id" class="form-select select2">
            <option value="">Decide later</option>
            @foreach($appraisers as $u)
                <option value="{{ $u->id }}" @selected(old('appraiser_id')==$u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
        <p class="text-xs text-slate-500 mt-1">
            You can also pick this when you send the card out, after the KPIs are set.
        </p>
    </div>
</div>

<div class="flex gap-3 mt-6">
    <button class="btn-primary"><i class="fas fa-arrow-right mr-1"></i> Create &amp; Set KPIs</button>
    <a href="{{ route('appraisals.index') }}" class="btn-secondary">Cancel</a>
</div>
</form>
@endsection
