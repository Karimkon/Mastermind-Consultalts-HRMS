@extends('layouts.app')
@section('title', 'Add KRA')
@section('content')
<x-page-header title="Add KRA — {{ $cycle->name }}">
    <a href="{{ route('bsc.cycles.show', $cycle) }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back to Cycle</a>
</x-page-header>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
<div class="lg:col-span-2 card p-6">
    <form method="POST" action="{{ route('bsc.kras.store', $cycle) }}">
        @csrf
        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Perspective <span class="text-red-500">*</span></label>
                <select name="perspective" class="form-input" required id="perspSelect">
                    <option value="">— Select Perspective —</option>
                    @php
                    $perspectives = [
                        'financial'        => ['label' => 'Financial (15%)',                'color' => 'text-blue-600'],
                        'customer'         => ['label' => 'Customer (15%)',                 'color' => 'text-green-600'],
                        'internal_process' => ['label' => 'Internal Business Process (40%)','color' => 'text-purple-600'],
                        'learning_growth'  => ['label' => 'Learning & Growth (30%)',        'color' => 'text-orange-600'],
                    ];
                    @endphp
                    @foreach($perspectives as $val => $meta)
                    <option value="{{ $val }}" {{ old('perspective', request('perspective')) == $val ? 'selected' : '' }}>
                        {{ $meta['label'] }}
                    </option>
                    @endforeach
                </select>
                @error('perspective')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="col-span-2">
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">KRA Name <span class="text-red-500">*</span></label>
                <input type="text" name="kra_name" value="{{ old('kra_name') }}" class="form-input" placeholder="e.g. Revenue Growth Rate" required>
                @error('kra_name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="col-span-2">
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Objective / Strategic Goal</label>
                <input type="text" name="objective" value="{{ old('objective') }}" class="form-input" placeholder="What strategic goal does this KRA support?">
            </div>

            <div class="col-span-2">
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Measure / How to Track</label>
                <input type="text" name="measure" value="{{ old('measure') }}" class="form-input" placeholder="e.g. Monthly sales report, NPS survey, etc.">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Target Value <span class="text-red-500">*</span></label>
                <input type="number" name="target" value="{{ old('target') }}" class="form-input" step="0.01" min="0" required placeholder="e.g. 100">
                @error('target')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Unit</label>
                <select name="unit" class="form-input">
                    @foreach(['%','Amount (ZAR)','Count','Score','Days','Hours','Rating'] as $u)
                    <option value="{{ $u }}" {{ old('unit') == $u ? 'selected' : '' }}>{{ $u }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Weightage (%) <span class="text-red-500">*</span></label>
                <input type="number" name="weightage" value="{{ old('weightage', 25) }}" class="form-input" step="0.1" min="0" max="100" required>
                <p class="text-xs text-slate-400 mt-1">% weight of this KRA within its perspective</p>
                @error('weightage')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Review Frequency <span class="text-red-500">*</span></label>
                <select name="review_frequency" class="form-input" required>
                    <option value="monthly" {{ old('review_frequency') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                    <option value="quarterly" {{ old('review_frequency', 'quarterly') == 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                    <option value="annual" {{ old('review_frequency') == 'annual' ? 'selected' : '' }}>Annual</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Sort Order</label>
                <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" class="form-input" min="0">
            </div>
        </div>

        <div class="flex gap-3 pt-5 mt-5 border-t border-slate-100">
            <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i> Add KRA</button>
            <a href="{{ route('bsc.cycles.show', $cycle) }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<div class="card p-5">
    <h4 class="font-bold text-slate-700 mb-3 text-sm flex items-center gap-2">
        <i class="fas fa-info-circle text-blue-400"></i> Rating Scale (1–5)
    </h4>
    <div class="space-y-2">
        @foreach([['5','Exceptional','Exceeded target significantly','green'],['4','Above Target','Above target','blue'],['3','On Target','Met target','yellow'],['2','Below Target','Slightly below','orange'],['1','Unacceptable','Did not meet','red']] as $r)
        <div class="flex items-center gap-3">
            <span class="w-7 h-7 rounded-full bg-{{ $r[3] }}-100 text-{{ $r[3] }}-700 font-bold text-sm flex items-center justify-center">{{ $r[0] }}</span>
            <div>
                <p class="text-xs font-semibold text-slate-700">{{ $r[1] }}</p>
                <p class="text-xs text-slate-400">{{ $r[2] }}</p>
            </div>
        </div>
        @endforeach
    </div>
    <div class="mt-4 p-3 bg-slate-50 rounded-lg text-xs text-slate-500">
        <strong>Weighted Index</strong> = Rating × (Weightage% ÷ 100)
    </div>
</div>
</div>
@endsection
