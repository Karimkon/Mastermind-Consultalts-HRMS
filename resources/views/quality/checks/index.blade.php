@extends('layouts.app')
@section('title', 'Quality Checks')
@section('content')

@php($sevColor = ['critical' => 'red', 'high' => 'orange', 'medium' => 'amber', 'low' => 'slate'])

<x-page-header title="Quality Checks" subtitle="Automated checks run against your live HR data - tune severity, weight and alerting">
    @if($latest)
    <a href="{{ route('quality.checks.results', ['run' => $latest->id]) }}" class="btn-secondary"><i class="fas fa-list-check mr-1"></i> Last results</a>
    @endif
    <form action="{{ route('quality.checks.run') }}" method="POST" class="inline">@csrf<input type="hidden" name="scope" value="all">
        <button class="btn-primary"><i class="fas fa-play mr-1"></i> Run all checks</button>
    </form>
</x-page-header>

@if(session('success'))
<div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

@foreach($checks as $function => $group)
<div class="mb-6">
    <div class="flex items-center justify-between mb-2">
        <h3 class="font-semibold text-slate-700">{{ ucwords(str_replace('_',' ', $function)) }}</h3>
        <form action="{{ route('quality.checks.run') }}" method="POST">@csrf<input type="hidden" name="scope" value="{{ $function }}">
            <button class="text-xs text-emerald-600 font-medium hover:underline"><i class="fas fa-play mr-1"></i> Run this group</button>
        </form>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 divide-y divide-slate-100">
        @foreach($group as $check)
        <div x-data="{ edit: false }" class="px-4 py-3">
            <div class="flex items-center gap-3">
                <div class="flex-1">
                    <p class="font-medium text-slate-800 {{ $check->is_active ? '' : 'line-through text-slate-400' }}">{{ $check->name }}</p>
                    <p class="text-xs text-slate-400">{{ $check->description }}</p>
                </div>
                <span class="text-xs font-mono text-slate-400 w-20 text-center">{{ $check->standard?->code ?? '-' }}</span>
                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $sevColor[$check->severity] ?? 'slate' }}-100 text-{{ $sevColor[$check->severity] ?? 'slate' }}-700">{{ $check->severity }}</span>
                <span class="text-xs text-slate-500 w-12 text-center">wt {{ $check->weight }}</span>
                <span class="w-14 text-center" title="Raises a non-conformity when it fails">{!! $check->auto_raise_nc ? '<i class="fas fa-bell text-emerald-500 text-xs"></i>' : '<span class="text-slate-300 text-xs">off</span>' !!}</span>
                <span class="text-xs text-slate-400 w-24 text-right">{{ $check->last_run_at?->diffForHumans() ?? 'never' }}</span>
                <button @click="edit = !edit" class="text-slate-400 hover:text-slate-700"><i class="fas fa-pen text-xs"></i></button>
            </div>
            <div x-show="edit" x-cloak class="mt-3 pt-3 border-t border-slate-100">
                <form action="{{ route('quality.checks.config', $check) }}" method="POST" class="flex flex-wrap items-end gap-3">
                    @csrf @method('PUT')
                    <div><label class="form-label">Severity</label><select name="severity" class="form-select">@foreach(['low','medium','high','critical'] as $s)<option value="{{ $s }}" {{ $check->severity === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>@endforeach</select></div>
                    <div><label class="form-label">Weight</label><input type="number" name="weight" value="{{ $check->weight }}" min="1" max="100" class="form-input w-24"></div>
                    <label class="flex items-center gap-2 text-sm text-slate-600 pb-2"><input type="checkbox" name="auto_raise_nc" value="1" {{ $check->auto_raise_nc ? 'checked' : '' }}> Raise NC on fail</label>
                    <label class="flex items-center gap-2 text-sm text-slate-600 pb-2"><input type="checkbox" name="is_active" value="1" {{ $check->is_active ? 'checked' : '' }}> Active</label>
                    <button class="btn-primary">Save</button>
                </form>
                <p class="text-[11px] text-slate-400 mt-2">Note: low/medium checks only raise a non-conformity if their severity is high or critical, regardless of this toggle.</p>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endforeach

@endsection
