@extends('layouts.app')
@section('title','Exit Process — ' . $employee->full_name)
@section('content')
<x-page-header title="Exit / Offboarding Process" :subtitle="$employee->full_name">
    <a href="{{ route('employees.show', $employee) }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</x-page-header>

@isset($exit)
{{-- Existing exit workflow --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-4">
        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-slate-800">Exit Summary</h3>
                <span class="badge-{{ $exit->status === 'completed' ? 'green' : ($exit->status === 'in_progress' ? 'yellow' : 'blue') }}">{{ ucwords(str_replace('_',' ',$exit->status)) }}</span>
            </div>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div><p class="text-slate-500">Exit Date</p><p class="font-semibold">{{ $exit->exit_date->format('M d, Y') }}</p></div>
                <div><p class="text-slate-500">Reason</p><p class="font-semibold capitalize">{{ $exit->reason }}</p></div>
            </div>
        </div>

        <form method="POST" action="{{ route('employees.exit.update', $employee) }}" class="card p-6 space-y-4">@csrf @method('PUT')
            <h3 class="font-semibold text-slate-800 mb-2">Exit Checklist</h3>

            <div class="space-y-3">
                @foreach([
                    ['equipment_returned', 'Equipment Returned'],
                    ['clearance_done', 'Clearance Certificate Issued'],
                    ['final_settlement_done', 'Final Settlement Done'],
                ] as [$field, $label])
                <label class="flex items-center gap-3 cursor-pointer p-3 rounded-lg border border-slate-200 hover:bg-slate-50">
                    <input type="checkbox" name="{{ $field }}" value="1" {{ $exit->$field ? 'checked' : '' }} class="rounded">
                    <span class="text-sm font-medium text-slate-700">{{ $label }}</span>
                </label>
                @endforeach
            </div>

            <div><label class="form-label">Settlement Amount (UGX)</label><input type="number" name="settlement_amount" value="{{ $exit->settlement_amount }}" class="form-input"></div>
            <div><label class="form-label">Equipment Notes</label><textarea name="equipment_notes" class="form-input" rows="2">{{ $exit->equipment_notes }}</textarea></div>
            <div><label class="form-label">Exit Interview Notes</label><textarea name="interview_notes" class="form-input" rows="3">{{ $exit->interview_notes }}</textarea></div>
            <div><label class="form-label">Status</label>
                <select name="status" class="form-select">
                    @foreach(['initiated','in_progress','completed'] as $s)
                    <option value="{{ $s }}" {{ $exit->status === $s ? 'selected' : '' }}>{{ ucwords(str_replace('_',' ',$s)) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update</button>
        </form>
    </div>

    <div class="card p-6">
        <div class="flex items-center gap-4 mb-4">
            <img src="{{ $employee->avatar_url }}" class="w-14 h-14 rounded-xl object-cover">
            <div>
                <h3 class="font-semibold text-slate-800">{{ $employee->full_name }}</h3>
                <p class="text-xs text-slate-500">{{ $employee->designation?->title }}</p>
            </div>
        </div>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between"><span class="text-slate-500">Department</span><span>{{ $employee->department?->name }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Hire Date</span><span>{{ $employee->hire_date?->format('M d, Y') }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Exit Date</span><span class="text-red-600 font-semibold">{{ $exit->exit_date->format('M d, Y') }}</span></div>
        </div>
    </div>
</div>

@else
{{-- Initiate exit --}}
<div class="card p-6 max-w-lg">
    <h3 class="font-semibold text-slate-800 mb-4">Initiate Exit Process</h3>
    <form method="POST" action="{{ route('employees.exit.store', $employee) }}" class="space-y-4">@csrf
        <div><label class="form-label">Exit Date *</label><input type="date" name="exit_date" class="form-input" required></div>
        <div><label class="form-label">Reason *</label>
            <select name="reason" class="form-select" required>
                @foreach(['resignation'=>'Resignation','termination'=>'Termination','retirement'=>'Retirement','redundancy'=>'Redundancy','other'=>'Other'] as $v=>$l)
                <option value="{{ $v }}">{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div class="bg-red-50 border border-red-200 rounded-lg p-3">
            <p class="text-sm text-red-700"><i class="fas fa-exclamation-triangle mr-2"></i>This will set the employee's status to <strong>Terminated</strong>.</p>
        </div>
        <button type="submit" class="btn-danger"><i class="fas fa-door-open"></i> Initiate Exit</button>
    </form>
</div>
@endisset
@endsection
