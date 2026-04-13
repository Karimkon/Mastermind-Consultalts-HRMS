@extends('layouts.app')
@section('title', 'Schedule Meeting')
@section('content')
<x-page-header title="Schedule Meeting">
    <a href="{{ route('meetings.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>
<form method="POST" action="{{ route('meetings.store') }}" class="max-w-2xl">
    @csrf
    <div class="card p-6 space-y-4">
        <div><label class="form-label">Meeting Title *</label><input type="text" name="title" class="form-input" required value="{{ old('title') }}"></div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="form-label">Start Date & Time *</label><input type="datetime-local" name="start_at" class="form-input" required value="{{ old('start_at') }}"></div>
            <div><label class="form-label">End Date & Time *</label><input type="datetime-local" name="end_at" class="form-input" required value="{{ old('end_at') }}"></div>
        </div>
        <div><label class="form-label">Location</label><input type="text" name="location" class="form-input" value="{{ old('location') }}" placeholder="Room / Zoom link"></div>
        <div><label class="form-label">Description</label><textarea name="description" rows="3" class="form-input">{{ old('description') }}</textarea></div>

        <div class="border-t border-slate-100 pt-4" x-data="{ recurring: '{{ old('recurrence') }}' }">
            <p class="text-sm font-semibold text-slate-700 mb-3">Recurrence</p>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Repeat</label>
                    <select name="recurrence" class="form-select" x-model="recurring">
                        <option value="">Does not repeat</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="biweekly">Every 2 weeks</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                <div x-show="recurring" x-cloak>
                    <label class="form-label">Repeat Until</label>
                    <input type="date" name="recurrence_end_date" class="form-input" value="{{ old('recurrence_end_date') }}">
                </div>
            </div>
        </div>

        <div><label class="form-label">Participants</label>
            <select name="participants[]" class="form-input select2" multiple>
                @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->full_name }} — {{ $e->department->name ?? '' }}</option>@endforeach
            </select>
            <p class="text-xs text-slate-400 mt-1">Invite emails will be queued to all selected participants.</p>
        </div>
        <button type="submit" class="btn-primary"><i class="fas fa-calendar-check mr-1"></i> Schedule Meeting</button>
    </div>
</form>
@endsection