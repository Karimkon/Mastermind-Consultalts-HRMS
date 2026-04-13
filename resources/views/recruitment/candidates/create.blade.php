@extends('layouts.app')
@section('title', 'Add Candidate')
@section('content')
<x-page-header title="Add Candidate" subtitle="Submit a new job application">
    <a href="{{ route('recruitment.candidates.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>
<form method="POST" action="{{ route('recruitment.candidates.store') }}" enctype="multipart/form-data" class="max-w-2xl">
    @csrf
    <div class="card p-6 space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2"><label class="form-label">Full Name *</label><input type="text" name="name" class="form-input" required value="{{ old('name') }}"></div>
            <div><label class="form-label">Email *</label><input type="email" name="email" class="form-input" required value="{{ old('email') }}"></div>
            <div><label class="form-label">Phone</label><input type="text" name="phone" class="form-input" value="{{ old('phone') }}"></div>
            <div class="col-span-2"><label class="form-label">Job Posting *</label>
                <select name="job_posting_id" class="form-input select2" required>
                    <option value="">Select job</option>
                    @foreach($jobs as $j)<option value="{{ $j->id }}" @selected(request('job_posting_id')==$j->id || old('job_posting_id')==$j->id)>{{ $j->title }}</option>@endforeach
                </select>
            </div>
            <div class="col-span-2"><label class="form-label">Resume (PDF/DOC)</label><input type="file" name="resume" class="form-input" accept=".pdf,.doc,.docx"></div>
            <div class="col-span-2"><label class="form-label">Notes</label><textarea name="notes" rows="3" class="form-input">{{ old('notes') }}</textarea></div>
        </div>
        <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i> Submit Application</button>
    </div>
</form>
@endsection