@extends('layouts.app')
@section('title', 'Edit BSC Cycle')
@section('content')
<x-page-header title="Edit BSC Cycle: {{ $cycle->name }}">
    <a href="{{ route('bsc.cycles.show', $cycle) }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>

<div class="max-w-2xl card p-6">
    <form method="POST" action="{{ route('bsc.cycles.update', $cycle) }}">
        @csrf @method('PUT')
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Cycle Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $cycle->name) }}" class="form-input" required>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Year</label>
                <input type="number" name="year" value="{{ old('year', $cycle->year) }}" class="form-input" min="2020" max="2050" required>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Period</label>
                <select name="period" class="form-input" required>
                    @foreach(['Q1','Q2','Q3','Q4','H1','H2','Annual'] as $p)
                    <option value="{{ $p }}" {{ old('period', $cycle->period) == $p ? 'selected' : '' }}>{{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Start Date</label>
                <input type="date" name="start_date" value="{{ old('start_date', $cycle->start_date->format('Y-m-d')) }}" class="form-input" required>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">End Date</label>
                <input type="date" name="end_date" value="{{ old('end_date', $cycle->end_date->format('Y-m-d')) }}" class="form-input" required>
            </div>
        </div>
        <div class="flex gap-3 pt-4 border-t border-slate-100">
            <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i> Save Changes</button>
            <a href="{{ route('bsc.cycles.show', $cycle) }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
