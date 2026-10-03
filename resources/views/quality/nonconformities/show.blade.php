@extends('layouts.app')
@section('title', $nonconformity->reference)
@section('content')

@php
    $sevColor = ['critical' => 'red', 'high' => 'orange', 'medium' => 'amber', 'low' => 'slate'];
    $statColor = ['open' => 'red', 'investigating' => 'amber', 'resolved' => 'emerald', 'closed' => 'slate', 'risk_accepted' => 'violet'];
    $actStat = ['planned' => 'slate', 'in_progress' => 'amber', 'completed' => 'blue', 'verified' => 'emerald', 'cancelled' => 'slate'];
@endphp

<x-page-header title="{{ $nonconformity->reference }}" subtitle="{{ $nonconformity->title }}">
    <a href="{{ route('quality.nonconformities.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>

@if(session('success'))
<div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        {{-- Detail --}}
        <div class="rounded-xl bg-white border border-slate-200 p-5">
            <div class="flex flex-wrap items-center gap-2 mb-3">
                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $sevColor[$nonconformity->severity] ?? 'slate' }}-100 text-{{ $sevColor[$nonconformity->severity] ?? 'slate' }}-700">{{ $nonconformity->severity }}</span>
                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $statColor[$nonconformity->status] ?? 'slate' }}-100 text-{{ $statColor[$nonconformity->status] ?? 'slate' }}-700">{{ str_replace('_',' ', $nonconformity->status) }}</span>
                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600">{{ ucwords(str_replace('_',' ', $nonconformity->hr_function)) }}</span>
                <span class="text-xs text-slate-400">via {{ $nonconformity->source }}</span>
            </div>
            <p class="text-slate-700 whitespace-pre-line">{{ $nonconformity->description }}</p>
            <dl class="grid grid-cols-2 gap-3 mt-4 text-sm">
                @if($nonconformity->subject_label)<div><dt class="text-slate-400">Record</dt><dd class="text-slate-700">{{ $nonconformity->subject_label }}</dd></div>@endif
                @if($nonconformity->check)<div><dt class="text-slate-400">From check</dt><dd class="text-slate-700">{{ $nonconformity->check->name }} ({{ $nonconformity->check->standard?->code }})</dd></div>@endif
                <div><dt class="text-slate-400">Detected</dt><dd class="text-slate-700">{{ $nonconformity->detected_at?->format('d M Y, H:i') }}</dd></div>
                @if($nonconformity->resolved_at)<div><dt class="text-slate-400">Resolved</dt><dd class="text-slate-700">{{ $nonconformity->resolved_at->format('d M Y, H:i') }}</dd></div>@endif
            </dl>
        </div>

        {{-- CAPA --}}
        <div class="rounded-xl bg-white border border-slate-200 p-5">
            <h3 class="font-semibold text-slate-700 mb-3">Corrective &amp; preventive actions</h3>
            <div class="space-y-3 mb-5">
                @forelse($nonconformity->actions as $a)
                <div class="rounded-lg border border-slate-200 p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $a->type }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $actStat[$a->status] ?? 'slate' }}-100 text-{{ $actStat[$a->status] ?? 'slate' }}-700">{{ str_replace('_',' ', $a->status) }}</span>
                    </div>
                    <p class="text-slate-700 mt-1">{{ $a->action }}</p>
                    @if($a->root_cause)<p class="text-xs text-slate-500 mt-1"><span class="font-medium">Root cause:</span> {{ $a->root_cause }}</p>@endif
                    <div class="flex items-center gap-4 text-xs text-slate-400 mt-2">
                        <span>Owner: {{ $a->owner?->name ?? 'unassigned' }}</span>
                        @if($a->due_date)<span>Due: {{ $a->due_date->format('d M Y') }}</span>@endif
                        @if($a->verified_at)<span class="text-emerald-600">Verified {{ $a->verified_at->format('d M Y') }}</span>@endif
                    </div>
                    <form action="{{ route('quality.actions.update', $a) }}" method="POST" class="flex items-center gap-2 mt-3">
                        @csrf @method('PUT')
                        <select name="status" class="form-select text-sm py-1 w-auto">
                            @foreach(['planned','in_progress','completed','verified','cancelled'] as $s)
                            <option value="{{ $s }}" {{ $a->status === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ', $s)) }}</option>
                            @endforeach
                        </select>
                        <button class="btn-secondary py-1 text-xs">Update</button>
                    </form>
                </div>
                @empty
                <p class="text-sm text-slate-400">No actions yet. Add a corrective or preventive action below.</p>
                @endforelse
            </div>

            <form action="{{ route('quality.actions.store', $nonconformity) }}" method="POST" class="border-t border-slate-100 pt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                @csrf
                <div><label class="form-label">Type</label><select name="type" class="form-select"><option value="corrective">Corrective</option><option value="preventive">Preventive</option></select></div>
                <div><label class="form-label">Owner</label><select name="owner_id" class="form-select"><option value="">Unassigned</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
                <div class="md:col-span-2"><label class="form-label">Action</label><textarea name="action" rows="2" required class="form-input" placeholder="What will be done?"></textarea></div>
                <div class="md:col-span-2"><label class="form-label">Root cause (optional)</label><input name="root_cause" class="form-input"></div>
                <div><label class="form-label">Due date</label><input type="date" name="due_date" class="form-input"></div>
                <div class="md:col-span-2 flex justify-end"><button class="btn-primary">Add action</button></div>
            </form>
        </div>
    </div>

    {{-- Manage --}}
    <div>
        <div class="rounded-xl bg-white border border-slate-200 p-5">
            <h3 class="font-semibold text-slate-700 mb-3">Manage</h3>
            <form action="{{ route('quality.nonconformities.update', $nonconformity) }}" method="POST" class="space-y-3">
                @csrf @method('PUT')
                <div>
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        @foreach(['open'=>'Open','investigating'=>'Investigating','resolved'=>'Resolved','closed'=>'Closed','risk_accepted'=>'Risk accepted'] as $k=>$v)
                        <option value="{{ $k }}" {{ $nonconformity->status === $k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Assigned to</label>
                    <select name="assigned_to" class="form-select">
                        <option value="">Unassigned</option>
                        @foreach($users as $u)<option value="{{ $u->id }}" {{ $nonconformity->assigned_to == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>@endforeach
                    </select>
                </div>
                <div><label class="form-label">Due date</label><input type="date" name="due_date" value="{{ $nonconformity->due_date?->format('Y-m-d') }}" class="form-input"></div>
                <div><label class="form-label">Resolution notes</label><textarea name="resolution_notes" rows="3" class="form-input">{{ $nonconformity->resolution_notes }}</textarea></div>
                <button class="btn-primary w-full justify-center">Save</button>
            </form>
        </div>
    </div>
</div>

@endsection
