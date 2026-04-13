@extends('layouts.app')
@section('title', 'New Performance Cycle')
@section('content')
<x-page-header title="New Performance Cycle">
    <a href="{{ route('performance.cycles.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>
<form method="POST" action="{{ route('performance.cycles.store') }}" class="max-w-lg">
    @csrf
    <div class="card p-6 space-y-4">
        <div><label class="form-label">Cycle Name *</label><input type="text" name="name" class="form-input" required value="{{ old('name') }}" placeholder="e.g. Q1 2026 Review"></div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="form-label">Year</label><input type="number" name="year" class="form-input" value="{{ old('year', date('Y')) }}"></div>
            <div><label class="form-label">Status</label>
                <select name="status" class="form-input">
                    @foreach(['draft','active','closed'] as $s)<option value="{{ $s }}" @selected(old('status')===$s)>{{ ucfirst($s) }}</option>@endforeach
                </select>
            </div>
            <div><label class="form-label">Start Date</label><input type="date" name="start_date" class="form-input" value="{{ old('start_date') }}"></div>
            <div><label class="form-label">End Date</label><input type="date" name="end_date" class="form-input" value="{{ old('end_date') }}"></div>
        </div>
        <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i> Create Cycle</button>
    </div>
</form>
@endsection