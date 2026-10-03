@extends('layouts.app')
@section('title', 'Quality Audits')
@section('content')

@php
    $statColor = ['planned' => 'slate', 'in_progress' => 'amber', 'completed' => 'emerald'];
    $scoreColor = fn ($s) => $s === null ? 'slate' : ($s >= 90 ? 'emerald' : ($s >= 75 ? 'amber' : 'red'));
@endphp

<x-page-header title="Quality Audits" subtitle="Planned reviews of each HR function against the standards">
    <a href="{{ route('quality.audits.create') }}" class="btn-primary"><i class="fas fa-plus mr-1"></i> Plan audit</a>
</x-page-header>

@if(session('success'))
<div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

<x-data-table>
    <thead><tr class="table-header"><th>Ref</th><th>Audit</th><th>Scope</th><th>Auditor</th><th>Items</th><th>Status</th><th>Score</th><th>Planned</th></tr></thead>
    <tbody>
    @forelse($audits as $a)
    <tr class="table-row cursor-pointer" onclick="window.location='{{ route('quality.audits.show', $a) }}'">
        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $a->reference }}</td>
        <td class="px-4 py-3 font-medium text-slate-800">{{ $a->title }}</td>
        <td class="px-4 py-3 text-sm text-slate-500">{{ $a->scope === 'all' ? 'All functions' : ucwords(str_replace('_',' ', $a->scope)) }}</td>
        <td class="px-4 py-3 text-sm text-slate-500">{{ $a->auditor?->name ?? '-' }}</td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $a->items_count }}</td>
        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $statColor[$a->status] ?? 'slate' }}-100 text-{{ $statColor[$a->status] ?? 'slate' }}-700">{{ str_replace('_',' ', $a->status) }}</span></td>
        <td class="px-4 py-3 font-semibold text-{{ $scoreColor($a->score) }}-600">{{ $a->score !== null ? rtrim(rtrim(number_format($a->score,1),'0'),'.').'%' : '-' }}</td>
        <td class="px-4 py-3 text-sm text-slate-500">{{ $a->planned_date?->format('d M Y') ?? '-' }}</td>
    </tr>
    @empty
    <tr><td colspan="8" class="px-4 py-10 text-center text-sm text-slate-400">No audits yet. Plan your first audit to begin.</td></tr>
    @endforelse
    </tbody>
</x-data-table>

<div class="mt-4">{{ $audits->links() }}</div>

@endsection
