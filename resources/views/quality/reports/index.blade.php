@extends('layouts.app')
@section('title', 'Quality Reports')
@section('content')

@php($scoreColor = fn ($s) => $s === null ? 'slate' : ($s >= 90 ? 'emerald' : ($s >= 75 ? 'amber' : 'red')))
@php($sevColor = ['critical' => 'red', 'high' => 'orange', 'medium' => 'amber', 'low' => 'slate'])
@php($maxTrend = collect($trend)->max('score') ?: 100)

<x-page-header title="Quality Reports" subtitle="Trends, non-conformity and CAPA status, audit history">
    <button onclick="window.print()" class="btn-secondary"><i class="fas fa-print mr-1"></i> Print</button>
</x-page-header>

{{-- Score trend --}}
<div class="rounded-xl bg-white border border-slate-200 p-5 mb-6">
    <h3 class="font-semibold text-slate-700 mb-4">Overall score trend</h3>
    @if(count($trend) < 2)
    <p class="text-sm text-slate-400">Not enough history yet - the trend builds up as scans run (one point per day).</p>
    @else
    <div class="flex items-end gap-1 h-40">
        @foreach($trend as $t)
        <div class="flex-1 flex flex-col items-center justify-end group" title="{{ $t['date'] }}: {{ $t['score'] }}%">
            <div class="w-full rounded-t bg-{{ $scoreColor($t['score']) }}-400" style="height: {{ max(2, $t['score']) }}%"></div>
        </div>
        @endforeach
    </div>
    <div class="flex justify-between text-[10px] text-slate-400 mt-2">
        <span>{{ $trend[0]['date'] }}</span>
        <span>{{ end($trend)['date'] }}</span>
    </div>
    @endif
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Functions --}}
    <div>
        <h3 class="font-semibold text-slate-700 mb-3">Latest score by function</h3>
        <div class="rounded-xl bg-white border border-slate-200 divide-y divide-slate-100">
            @forelse($functions as $f)
            <div class="flex items-center gap-4 px-5 py-3">
                <span class="w-40 text-sm font-medium text-slate-700">{{ $f->label }}</span>
                <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full bg-{{ $scoreColor($f->score) }}-500" style="width: {{ max(2,min(100,$f->score)) }}%"></div></div>
                <span class="w-12 text-right text-sm font-semibold text-{{ $scoreColor($f->score) }}-600">{{ rtrim(rtrim(number_format($f->score,1),'0'),'.') }}%</span>
            </div>
            @empty
            <p class="px-5 py-6 text-sm text-slate-400">No snapshots yet.</p>
            @endforelse
        </div>
    </div>

    {{-- NC + CAPA status --}}
    <div class="space-y-6">
        <div>
            <h3 class="font-semibold text-slate-700 mb-3">Non-conformities by status</h3>
            <div class="rounded-xl bg-white border border-slate-200 p-5 grid grid-cols-2 gap-3">
                @foreach(['open','investigating','resolved','closed','risk_accepted'] as $st)
                <div class="flex items-center justify-between"><span class="text-sm text-slate-500">{{ ucfirst(str_replace('_',' ', $st)) }}</span><span class="font-semibold text-slate-800">{{ $ncByStatus[$st] ?? 0 }}</span></div>
                @endforeach
            </div>
        </div>
        <div>
            <h3 class="font-semibold text-slate-700 mb-3">Open NCs by severity</h3>
            <div class="rounded-xl bg-white border border-slate-200 p-5 flex flex-wrap gap-2">
                @forelse(['critical','high','medium','low'] as $sv)
                @if(($ncBySeverity[$sv] ?? 0) > 0)
                <span class="px-3 py-1 rounded-full text-sm font-semibold bg-{{ $sevColor[$sv] }}-100 text-{{ $sevColor[$sv] }}-700">{{ $ncBySeverity[$sv] }} {{ $sv }}</span>
                @endif
                @empty @endforelse
                @if(collect($ncBySeverity)->sum() === 0)<span class="text-sm text-emerald-600"><i class="fas fa-check-circle mr-1"></i> None open</span>@endif
            </div>
        </div>
        <div>
            <h3 class="font-semibold text-slate-700 mb-3">Corrective actions by status</h3>
            <div class="rounded-xl bg-white border border-slate-200 p-5 grid grid-cols-2 gap-3">
                @foreach(['planned','in_progress','completed','verified','cancelled'] as $st)
                <div class="flex items-center justify-between"><span class="text-sm text-slate-500">{{ ucfirst(str_replace('_',' ', $st)) }}</span><span class="font-semibold text-slate-800">{{ $capaByStatus[$st] ?? 0 }}</span></div>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- Audit history --}}
<h3 class="font-semibold text-slate-700 mb-3 mt-6">Recent audits</h3>
<x-data-table>
    <thead><tr class="table-header"><th>Ref</th><th>Title</th><th>Scope</th><th>Status</th><th>Score</th><th>Date</th></tr></thead>
    <tbody>
    @forelse($audits as $a)
    <tr class="table-row cursor-pointer" onclick="window.location='{{ route('quality.audits.show', $a) }}'">
        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $a->reference }}</td>
        <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $a->title }}</td>
        <td class="px-4 py-3 text-sm text-slate-500">{{ $a->scope === 'all' ? 'All' : ucwords(str_replace('_',' ', $a->scope)) }}</td>
        <td class="px-4 py-3 text-sm text-slate-500">{{ str_replace('_',' ', $a->status) }}</td>
        <td class="px-4 py-3 text-sm font-semibold text-{{ $scoreColor($a->score) }}-600">{{ $a->score !== null ? rtrim(rtrim(number_format($a->score,1),'0'),'.').'%' : '-' }}</td>
        <td class="px-4 py-3 text-sm text-slate-500">{{ ($a->completed_at ?? $a->created_at)?->format('d M Y') }}</td>
    </tr>
    @empty
    <tr><td colspan="6" class="px-4 py-6 text-center text-sm text-slate-400">No audits recorded yet.</td></tr>
    @endforelse
    </tbody>
</x-data-table>

@endsection
