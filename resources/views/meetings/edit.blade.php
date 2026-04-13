@extends('layouts.app')
@section('title', 'Edit Meeting')
@section('content')
<x-page-header title="Edit Meeting" :subtitle="$meeting->title">
    <a href="{{ route('meetings.show', $meeting) }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>
<form method="POST" action="{{ route('meetings.update', $meeting) }}" class="max-w-2xl">
    @csrf @method('PUT')
    <div class="card p-6 space-y-4">
        <div><label class="form-label">Title *</label><input type="text" name="title" class="form-input" required value="{{ old('title', $meeting->title) }}"></div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="form-label">Start</label><input type="datetime-local" name="start_at" class="form-input" value="{{ old('start_at', \Carbon\Carbon::parse($meeting->start_at)->format('Y-m-d\TH:i')) }}"></div>
            <div><label class="form-label">End</label><input type="datetime-local" name="end_at" class="form-input" value="{{ old('end_at', \Carbon\Carbon::parse($meeting->end_at)->format('Y-m-d\TH:i')) }}"></div>
        </div>
        <div><label class="form-label">Location</label><input type="text" name="location" class="form-input" value="{{ old('location', $meeting->location) }}"></div>
        <div><label class="form-label">Description</label><textarea name="description" rows="3" class="form-input">{{ old('description', $meeting->description) }}</textarea></div>
        <div><label class="form-label">Participants</label>
            <select name="participants[]" class="form-input select2" multiple>
                @foreach($employees as $e)<option value="{{ $e->id }}" @selected($meeting->participants->pluck('employee_id')->contains($e->id))>{{ $e->full_name }}</option>@endforeach
            </select>
        </div>
        <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i> Update Meeting</button>
    </div>
</form>
@endsection