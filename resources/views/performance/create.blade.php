@extends('layouts.app')
@section('title', 'New Performance Review')
@section('content')
<x-page-header title="Submit Performance Review">
    <a href="{{ route('performance.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>
<form method="POST" action="{{ route('performance.store') }}" class="max-w-3xl">
    @csrf
    <div class="card p-6 space-y-5">
        <div class="grid grid-cols-2 gap-4">
            <div><label class="form-label">Employee *</label>
                <select name="employee_id" class="form-input select2" required>
                    <option value="">Select employee</option>
                    @foreach($employees as $e)<option value="{{ $e->id }}" @selected(old('employee_id')==$e->id)>{{ $e->full_name }}</option>@endforeach
                </select>
            </div>
            <div><label class="form-label">Performance Cycle *</label>
                <select name="cycle_id" class="form-input select2" required>
                    <option value="">Select cycle</option>
                    @foreach($cycles as $c)<option value="{{ $c->id }}" @selected(old('cycle_id')==$c->id)>{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div><label class="form-label">Review Type</label>
                <select name="type" class="form-input">
                    @foreach(['self','manager','peer'] as $t)<option value="{{ $t }}" @selected(old('type')===$t)>{{ ucfirst($t) }}</option>@endforeach
                </select>
            </div>
        </div>
        <div>
            <h3 class="font-semibold text-slate-700 mb-3">KPI Ratings</h3>
            @foreach($kpis as $kpi)
            <div class="flex items-center gap-4 py-3 border-b border-slate-100 last:border-0">
                <div class="flex-1">
                    <p class="text-sm font-medium text-slate-800">{{ $kpi->name }}</p>
                    <p class="text-xs text-slate-500">{{ $kpi->category }} — Weight: {{ $kpi->weight }}%</p>
                </div>
                <div class="flex gap-1" x-data="{ rating: 0 }">
                    @for($i=1; $i<=5; $i++)
                    <button type="button" @click="rating = {{ $i }}" class="w-8 h-8 rounded-lg text-sm font-medium transition-colors" :class="rating >= {{ $i }} ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'">{{ $i }}</button>
                    @endfor
                    <input type="hidden" name="ratings[{{ $kpi->id }}]" x-bind:value="rating">
                </div>
            </div>
            @endforeach
        </div>
        <div><label class="form-label">Overall Comments</label><textarea name="comments" rows="4" class="form-input">{{ old('comments') }}</textarea></div>
        <button type="submit" class="btn-primary"><i class="fas fa-paper-plane mr-1"></i> Submit Review</button>
    </div>
</form>
@endsection