@extends('layouts.app')
@section('title', 'Schedule Interview')
@section('content')
<x-page-header title="Schedule Interview">
    <a href="{{ route('recruitment.interviews.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>
<form method="POST" action="{{ route('recruitment.interviews.store') }}" class="max-w-xl">
    @csrf
    <div class="card p-6 space-y-4">
        <div><label class="form-label">Candidate *</label>
            <select name="candidate_id" class="form-input select2" required>
                <option value="">Select candidate</option>
                @foreach($candidates as $c)<option value="{{ $c->id }}" @selected(request('candidate_id')==$c->id || old('candidate_id')==$c->id)>{{ $c->name }} — {{ $c->jobPosting->title ?? '' }}</option>@endforeach
            </select>
        </div>
        <div><label class="form-label">Interviewer *</label>
            <select name="interviewer_id" class="form-input select2" required>
                <option value="">Select interviewer</option>
                @foreach($employees as $e)<option value="{{ $e->id }}" @selected(old('interviewer_id')==$e->id)>{{ $e->full_name }}</option>@endforeach
            </select>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="form-label">Interview Type</label>
                <select name="type" class="form-input">
                    @foreach(['phone','video','technical','hr','final'] as $t)<option value="{{ $t }}" @selected(old('type')===$t)>{{ ucfirst($t) }}</option>@endforeach
                </select>
            </div>
            <div><label class="form-label">Scheduled At *</label><input type="datetime-local" name="scheduled_at" class="form-input" value="{{ old('scheduled_at') }}" required></div>
        </div>
        <div><label class="form-label">Notes</label><textarea name="notes" rows="3" class="form-input">{{ old('notes') }}</textarea></div>
        <button type="submit" class="btn-primary"><i class="fas fa-calendar-check mr-1"></i> Schedule</button>
    </div>
</form>
@endsection