@extends('layouts.app')
@section('title', 'Interview Details')
@section('content')
<x-page-header title="Interview Details" :subtitle="$interview->candidate->name ?? ''">
    <a href="{{ route('recruitment.interviews.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>
<div class="max-w-2xl">
    <div class="card p-6 space-y-4">
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div><span class="text-slate-500">Candidate</span><p class="font-medium">{{ $interview->candidate->name ?? '—' }}</p></div>
            <div><span class="text-slate-500">Job</span><p class="font-medium">{{ $interview->candidate->jobPosting->title ?? '—' }}</p></div>
            <div><span class="text-slate-500">Type</span><p class="font-medium">{{ ucfirst($interview->type) }}</p></div>
            <div><span class="text-slate-500">Scheduled</span><p class="font-medium">{{ \Carbon\Carbon::parse($interview->scheduled_at)->format('M d, Y H:i') }}</p></div>
            <div><span class="text-slate-500">Interviewer</span><p class="font-medium">{{ $interview->interviewer->full_name ?? '—' }}</p></div>
            <div><span class="text-slate-500">Status</span><span class="badge badge-blue">{{ ucfirst($interview->status) }}</span></div>
        </div>
        <form method="POST" action="{{ route('recruitment.interviews.update', $interview) }}" class="pt-4 border-t border-slate-100 space-y-4">
            @csrf @method('PUT')
            <div class="grid grid-cols-2 gap-4">
                <div><label class="form-label">Update Status</label>
                    <select name="status" class="form-input">
                        @foreach(['scheduled','completed','cancelled','no-show'] as $s)<option value="{{ $s }}" @selected($interview->status===$s)>{{ ucfirst($s) }}</option>@endforeach
                    </select>
                </div>
                <div><label class="form-label">Rating (1-5)</label>
                    <input type="number" name="rating" class="form-input" min="1" max="5" value="{{ $interview->rating }}">
                </div>
            </div>
            <div><label class="form-label">Feedback</label><textarea name="feedback" rows="4" class="form-input">{{ $interview->feedback }}</textarea></div>
            <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i> Save Feedback</button>
        </form>
    </div>
</div>
@endsection