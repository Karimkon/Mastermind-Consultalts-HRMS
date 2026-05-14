@extends('layouts.app')
@section('title', 'Create BSC Cycle')
@section('content')
<x-page-header title="Create BSC Cycle">
    <a href="{{ route('bsc.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 card p-6">
        <form method="POST" action="{{ route('bsc.cycles.store') }}">
            @csrf
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Cycle Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" class="form-input" placeholder="e.g. Q1 2025 Performance Appraisal" required>
                    @error('name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Year <span class="text-red-500">*</span></label>
                    <input type="number" name="year" value="{{ old('year', date('Y')) }}" class="form-input" min="2020" max="2050" required>
                    @error('year')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Period <span class="text-red-500">*</span></label>
                    <select name="period" class="form-input" required>
                        <option value="">— Select Period —</option>
                        @foreach(['Q1','Q2','Q3','Q4','H1','H2','Annual'] as $p)
                        <option value="{{ $p }}" {{ old('period') == $p ? 'selected' : '' }}>{{ $p }}</option>
                        @endforeach
                    </select>
                    @error('period')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Start Date <span class="text-red-500">*</span></label>
                    <input type="date" name="start_date" value="{{ old('start_date') }}" class="form-input" required>
                    @error('start_date')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">End Date <span class="text-red-500">*</span></label>
                    <input type="date" name="end_date" value="{{ old('end_date') }}" class="form-input" required>
                    @error('end_date')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="flex gap-3 pt-4 border-t border-slate-100">
                <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i> Create Cycle</button>
                <a href="{{ route('bsc.index') }}" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    {{-- BSC Perspective Weights Info --}}
    <div class="space-y-3">
        <div class="card p-4">
            <h4 class="font-bold text-slate-700 mb-3 flex items-center gap-2">
                <i class="fas fa-info-circle text-blue-400"></i> BSC Perspective Weights
            </h4>
            <p class="text-xs text-slate-500 mb-3">These weights are fixed per the Operations Balanced Scorecard framework:</p>
            @php
            $perspectives = [
                ['name'=>'Financials',               'weight'=>'15%', 'color'=>'blue'],
                ['name'=>'Customer',                 'weight'=>'15%', 'color'=>'green'],
                ['name'=>'Internal Business Process','weight'=>'40%', 'color'=>'purple'],
                ['name'=>'Learning & Growth',        'weight'=>'30%', 'color'=>'orange'],
            ];
            @endphp
            @foreach($perspectives as $p)
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-slate-700">{{ $p['name'] }}</span>
                <span class="font-bold text-{{ $p['color'] }}-600 bg-{{ $p['color'] }}-50 px-2 py-0.5 rounded text-sm">{{ $p['weight'] }}</span>
            </div>
            @endforeach
            <div class="mt-3 pt-3 border-t border-slate-100 flex justify-between">
                <span class="text-sm font-bold text-slate-700">Total</span>
                <span class="font-bold text-slate-900">100%</span>
            </div>
        </div>
        <div class="card p-4 bg-amber-50 border-amber-200">
            <h4 class="font-bold text-amber-700 mb-2 text-sm flex items-center gap-2">
                <i class="fas fa-lightbulb"></i> Next Steps After Creation
            </h4>
            <ol class="text-xs text-amber-700 space-y-1 list-decimal list-inside">
                <li>Add KRAs for each perspective</li>
                <li>Set weightage for each KRA (must total 100% per perspective)</li>
                <li>Activate the cycle</li>
                <li>Initialize appraisals for employees</li>
                <li>Account Managers appraise their employees</li>
            </ol>
        </div>
    </div>
</div>
@endsection
