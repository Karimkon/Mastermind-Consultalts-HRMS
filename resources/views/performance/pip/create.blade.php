@extends('layouts.app')
@section('title','New PIP')
@section('content')
<x-page-header title="Create Performance Improvement Plan">
    <a href="{{ route('pips.index') }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</x-page-header>
<div class="card p-6 max-w-2xl">
    <form method="POST" action="{{ route('pips.store') }}" class="space-y-4">@csrf
        <div><label class="form-label">Employee *</label>
            <select name="employee_id" class="form-select select2" required>
                <option value="">Select employee</option>
                @foreach($employees as $emp)<option value="{{ $emp->id }}">{{ $emp->full_name }} — {{ $emp->department?->name }}</option>@endforeach
            </select>
        </div>
        <div><label class="form-label">Cycle</label>
            <select name="cycle_id" class="form-select">
                <option value="">None</option>
                @foreach($cycles as $c)<option value="{{ $c->id }}">{{ $c->name ?? $c->year }}</option>@endforeach
            </select>
        </div>
        <div><label class="form-label">PIP Title *</label><input type="text" name="title" class="form-input" required placeholder="e.g. Sales Performance Improvement Q2 2026"></div>
        <div><label class="form-label">Description / Context</label><textarea name="description" class="form-input" rows="3" placeholder="Background and reason for PIP..."></textarea></div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="form-label">Start Date *</label><input type="date" name="start_date" class="form-input" required></div>
            <div><label class="form-label">End Date *</label><input type="date" name="end_date" class="form-input" required></div>
        </div>
        <div>
            <label class="form-label">Objectives / Milestones</label>
            <div id="objectives" class="space-y-2">
                <input type="text" name="objectives[]" class="form-input" placeholder="Objective 1...">
                <input type="text" name="objectives[]" class="form-input" placeholder="Objective 2...">
            </div>
            <button type="button" onclick="document.getElementById('objectives').insertAdjacentHTML('beforeend','<input type=text name=objectives[] class=form-input placeholder=Add objective...>')" class="text-xs text-blue-600 mt-2">+ Add objective</button>
        </div>
        <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Create PIP</button>
    </form>
</div>
@endsection
