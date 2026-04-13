@extends('layouts.app')
@section('title', 'New Job Posting')
@section('content')
<x-page-header title="New Job Posting" subtitle="Create a new position requisition">
    <a href="{{ route('recruitment.jobs.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>
<form method="POST" action="{{ route('recruitment.jobs.store') }}" class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    @csrf
    <div class="xl:col-span-2 space-y-6">
        <div class="card p-6">
            <h3 class="font-semibold text-slate-700 mb-4">Job Details</h3>
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2"><label class="form-label">Job Title *</label><input type="text" name="title" class="form-input" value="{{ old('title') }}" required></div>
                <div><label class="form-label">Department</label>
                    <select name="department_id" class="form-input select2">
                        <option value="">Select department</option>
                        @foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id')==$d->id)>{{ $d->name }}</option>@endforeach
                    </select>
                </div>
                <div><label class="form-label">Employment Type</label>
                    <select name="type" class="form-input">
                        @foreach(['full-time','part-time','contract','internship'] as $t)<option value="{{ $t }}" @selected(old('type')===$t)>{{ ucfirst($t) }}</option>@endforeach
                    </select>
                </div>
                <div><label class="form-label">Location</label><input type="text" name="location" class="form-input" value="{{ old('location') }}"></div>
                <div><label class="form-label">Deadline</label><input type="date" name="deadline" class="form-input" value="{{ old('deadline') }}"></div>
                <div><label class="form-label">Vacancies</label><input type="number" name="vacancies" class="form-input" value="{{ old('vacancies', 1) }}" min="1"></div>
                <div><label class="form-label">Status</label>
                    <select name="status" class="form-input">
                        @foreach(['draft','open','on-hold','closed'] as $s)<option value="{{ $s }}" @selected(old('status')===$s)>{{ ucfirst($s) }}</option>@endforeach
                    </select>
                </div>
                <div><label class="form-label">Salary Min</label><input type="number" name="salary_min" class="form-input" value="{{ old('salary_min') }}" placeholder="0.00"></div>
                <div><label class="form-label">Salary Max</label><input type="number" name="salary_max" class="form-input" value="{{ old('salary_max') }}" placeholder="0.00"></div>
            </div>
        </div>
        <div class="card p-6">
            <h3 class="font-semibold text-slate-700 mb-4">Description & Requirements</h3>
            <div class="space-y-4">
                <div><label class="form-label">Job Description</label><textarea name="description" rows="6" class="form-input">{{ old('description') }}</textarea></div>
                <div><label class="form-label">Requirements</label><textarea name="requirements" rows="4" class="form-input" placeholder="List key skills/requirements for AI scoring...">{{ old('requirements') }}</textarea></div>
                <div><label class="form-label">Benefits</label><textarea name="benefits" rows="3" class="form-input">{{ old('benefits') }}</textarea></div>
            </div>
        </div>
    </div>
    <div class="space-y-4">
        <div class="card p-5">
            <h3 class="font-semibold text-slate-700 mb-3">Publish</h3>
            <button type="submit" class="btn-primary w-full"><i class="fas fa-save mr-1"></i> Save Job Posting</button>
        </div>
    </div>
</form>
@endsection