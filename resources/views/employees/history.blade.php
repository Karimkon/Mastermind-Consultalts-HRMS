@extends('layouts.app')
@section('title', 'Employment History')
@section('content')
<x-page-header title="Employment History" :subtitle="$employee->full_name">
    <a href="{{ route('employees.show', $employee) }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
    <button @click="$dispatch('open-modal-add-history')" class="btn-primary"><i class="fas fa-plus mr-1"></i> Add Entry</button>
</x-page-header>

<div class="max-w-2xl">
    <div class="relative">
        <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-blue-100"></div>
        <div class="space-y-4">
        @forelse($employee->history as $h)
        <div class="relative flex gap-4 pl-12">
            <div class="absolute left-2.5 top-1 w-3 h-3 rounded-full bg-blue-600 border-2 border-white shadow"></div>
            <div class="card p-4 flex-1">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="font-semibold text-slate-800">{{ $h->position }}</p>
                        @if($h->company_name)
                        <p class="text-sm text-slate-500">{{ $h->company_name }}</p>
                        @elseif($h->department?->name)
                        <p class="text-sm text-slate-500">{{ $h->department->name }}</p>
                        @endif
                    </div>
                    <span class="text-xs text-slate-400">
                        {{ $h->start_date?->format('M Y') }} – {{ $h->end_date ? $h->end_date->format('M Y') : 'Present' }}
                    </span>
                </div>
                @if($h->reason_for_change)
                <p class="text-xs text-slate-500 mt-1">{{ $h->reason_for_change }}</p>
                @endif
            </div>
        </div>
        @empty
        <div class="card p-8 text-center text-slate-400 ml-4"><p>No employment history.</p></div>
        @endforelse
        </div>
    </div>
</div>

<x-modal id="add-history" title="Add History Entry">
    <form method="POST" action="{{ route('employees.history.store', $employee) }}" class="space-y-4">
        @csrf
        <div><label class="form-label">Position / Role <span class="text-red-500">*</span></label><input type="text" name="position" class="form-input" required></div>
        <div><label class="form-label">Company / Organisation</label><input type="text" name="company_name" class="form-input" placeholder="Company or department name"></div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="form-label">Start Date</label><input type="date" name="start_date" class="form-input"></div>
            <div><label class="form-label">End Date</label><input type="date" name="end_date" class="form-input"></div>
        </div>
        <div><label class="form-label">Reason / Notes</label><textarea name="reason_for_change" rows="2" class="form-input"></textarea></div>
        <button type="submit" class="btn-primary w-full">Save Entry</button>
    </form>
</x-modal>
@endsection
