@extends('layouts.app')
@section('title', 'Plan Audit')
@section('content')

<x-page-header title="Plan an Audit" subtitle="A checklist is built automatically from the active standards in scope">
    <a href="{{ route('quality.audits.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>

<div class="max-w-2xl rounded-xl bg-white border border-slate-200 p-6">
    <form action="{{ route('quality.audits.store') }}" method="POST" class="space-y-4">
        @csrf
        <div><label class="form-label">Title</label><input name="title" required class="form-input" placeholder="e.g. Q3 Employee Central data audit"></div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Scope</label>
                <select name="scope" class="form-select">
                    <option value="all">All functions</option>
                    @foreach(['employee_central'=>'Employee Central','payroll'=>'Payroll','recruitment'=>'Recruitment','attendance'=>'Attendance','leave'=>'Leave','performance'=>'Performance','compliance'=>'Compliance'] as $k=>$v)
                    <option value="{{ $k }}">{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Type</label>
                <select name="type" class="form-select"><option value="internal">Internal</option><option value="external">External</option><option value="process">Process</option></select>
            </div>
            <div>
                <label class="form-label">Auditor</label>
                <select name="auditor_id" class="form-select"><option value="">Unassigned</option>@foreach($auditors as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
            </div>
            <div>
                <label class="form-label">Department (optional)</label>
                <select name="department_id" class="form-select"><option value="">All / N/A</option>@foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select>
            </div>
            <div><label class="form-label">Planned date</label><input type="date" name="planned_date" class="form-input"></div>
        </div>
        <div class="rounded-lg bg-slate-50 border border-slate-200 p-3 text-sm text-slate-500">
            <i class="fas fa-circle-info mr-1"></i> The checklist is generated from the active standards in the chosen scope. You score each item while conducting the audit.
        </div>
        <div class="flex justify-end"><button class="btn-primary">Create audit</button></div>
    </form>
</div>

@endsection
