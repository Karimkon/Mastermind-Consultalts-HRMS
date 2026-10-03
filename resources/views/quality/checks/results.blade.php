@extends('layouts.app')
@section('title', 'Scan Results')
@section('content')

@php
    $statusColor = ['fail' => 'red', 'warning' => 'amber', 'pass' => 'emerald'];
    $scoreColor = fn ($s) => $s === null ? 'slate' : ($s >= 90 ? 'emerald' : ($s >= 75 ? 'amber' : 'red'));
@endphp

<x-page-header title="Quality Scan Results" subtitle="Run #{{ $run->id }} &middot; {{ $run->completed_at?->format('d M Y, H:i') }}">
    <a href="{{ route('quality.checks.index') }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Checks</a>
    <a href="{{ route('quality.dashboard') }}" class="btn-secondary"><i class="fas fa-gauge-high mr-1"></i> Dashboard</a>
</x-page-header>

<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
    <div class="rounded-xl bg-white border border-slate-200 p-4 text-center">
        <p class="text-xs text-slate-400 uppercase">Score</p>
        <p class="text-2xl font-bold text-{{ $scoreColor($run->score) }}-600">{{ $run->score !== null ? rtrim(rtrim(number_format($run->score,1),'0'),'.') : '-' }}%</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 p-4 text-center"><p class="text-xs text-slate-400 uppercase">Scanned</p><p class="text-2xl font-bold text-slate-700">{{ number_format($run->subjects_scanned) }}</p></div>
    <div class="rounded-xl bg-white border border-slate-200 p-4 text-center"><p class="text-xs text-slate-400 uppercase">Passed</p><p class="text-2xl font-bold text-emerald-600">{{ number_format($run->passed) }}</p></div>
    <div class="rounded-xl bg-white border border-slate-200 p-4 text-center"><p class="text-xs text-slate-400 uppercase">Failed</p><p class="text-2xl font-bold text-red-600">{{ number_format($run->failed) }}</p></div>
    <div class="rounded-xl bg-white border border-slate-200 p-4 text-center"><p class="text-xs text-slate-400 uppercase">Warnings</p><p class="text-2xl font-bold text-amber-600">{{ number_format($run->warnings) }}</p></div>
</div>

{{-- Filters --}}
<form method="GET" class="flex flex-wrap items-center gap-2 mb-4">
    <input type="hidden" name="run" value="{{ $run->id }}">
    @foreach(['fail' => 'Failures', 'warning' => 'Warnings', 'pass' => 'Passes', 'all' => 'All'] as $val => $label)
    <a href="{{ route('quality.checks.results', array_filter(['run' => $run->id, 'status' => $val, 'check' => $checkId])) }}"
       class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $status === $val ? 'bg-slate-800 text-white' : 'bg-white border border-slate-200 text-slate-600' }}">{{ $label }}</a>
    @endforeach
    <select name="check" onchange="this.form.submit()" class="ml-auto rounded-lg border-slate-200 text-sm">
        <option value="">All checks</option>
        @foreach($checksInRun as $c)
        <option value="{{ $c->id }}" {{ (string)$checkId === (string)$c->id ? 'selected' : '' }}>{{ $c->name }}</option>
        @endforeach
    </select>
</form>

<x-data-table>
    <thead><tr class="table-header"><th>Status</th><th>Check</th><th>Record</th><th>Finding</th></tr></thead>
    <tbody>
    @forelse($results as $res)
    <tr class="table-row">
        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $statusColor[$res->status] ?? 'slate' }}-100 text-{{ $statusColor[$res->status] ?? 'slate' }}-700">{{ $res->status }}</span></td>
        <td class="px-4 py-3 text-sm font-medium text-slate-700">{{ $res->check?->name }}</td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $res->subject_label ?? '-' }}</td>
        <td class="px-4 py-3 text-sm text-slate-500">{{ $res->message }}</td>
    </tr>
    @empty
    <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">No {{ $status === 'all' ? '' : $status }} results.</td></tr>
    @endforelse
    </tbody>
</x-data-table>

<div class="mt-4">{{ $results->links() }}</div>

@endsection
