@extends('layouts.app')
@section('title', 'Compliance')
@section('content')

@php($sevColor = ['critical' => 'red', 'high' => 'orange', 'medium' => 'amber', 'low' => 'slate'])
@php($scoreColor = $complianceScore === null ? 'slate' : ($complianceScore >= 90 ? 'emerald' : ($complianceScore >= 75 ? 'amber' : 'red')))

<x-page-header title="Compliance" subtitle="Statutory and right-to-work obligations across the workforce">
    <a href="{{ route('quality.dashboard') }}" class="btn-secondary"><i class="fas fa-gauge-high mr-1"></i> Dashboard</a>
</x-page-header>

@if(! $latest)
<div class="rounded-xl border-2 border-dashed border-slate-200 bg-white p-10 text-center">
    <i class="fas fa-scale-balanced text-4xl text-slate-300 mb-3"></i>
    <p class="text-slate-500 text-sm">Run a quality scan to populate the compliance picture.</p>
    <form action="{{ route('quality.checks.run') }}" method="POST" class="mt-3">@csrf<input type="hidden" name="scope" value="all"><button class="btn-primary">Run scan</button></form>
</div>
@else

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="rounded-xl bg-white border border-slate-200 p-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Compliance score</p>
        <p class="text-4xl font-bold text-{{ $scoreColor }}-600 mt-2">{{ $complianceScore !== null ? rtrim(rtrim(number_format($complianceScore,1),'0'),'.').'%' : 'n/a' }}</p>
        <p class="text-xs text-slate-400 mt-1">Last scan {{ $latest->completed_at?->diffForHumans() }}</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 p-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Records flagged</p>
        <p class="text-4xl font-bold text-slate-800 mt-2">{{ number_format($totalFlagged) }}</p>
        <p class="text-xs text-slate-400 mt-1">across statutory checks</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 p-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Open compliance NCs</p>
        <p class="text-4xl font-bold text-slate-800 mt-2">{{ $openNc->count() }}</p>
        <a href="{{ route('quality.nonconformities.index', ['function' => 'compliance']) }}" class="text-xs text-emerald-600 font-medium mt-1 inline-block">View &rarr;</a>
    </div>
</div>

<h3 class="font-semibold text-slate-700 mb-3">Statutory checks</h3>
<x-data-table>
    <thead><tr class="table-header"><th>Requirement</th><th>Function</th><th>Severity</th><th>Standard</th><th class="text-right">Flagged</th></tr></thead>
    <tbody>
    @foreach($checks as $c)
    <tr class="table-row">
        <td class="px-4 py-3 font-medium text-slate-800">{{ $c['name'] }}</td>
        <td class="px-4 py-3 text-sm text-slate-500">{{ ucwords(str_replace('_',' ', $c['function'])) }}</td>
        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $sevColor[$c['severity']] ?? 'slate' }}-100 text-{{ $sevColor[$c['severity']] ?? 'slate' }}-700">{{ $c['severity'] }}</span></td>
        <td class="px-4 py-3 text-xs font-mono text-slate-400">{{ $c['standard'] ?? '-' }}</td>
        <td class="px-4 py-3 text-right font-semibold {{ $c['fails'] > 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ $c['fails'] > 0 ? $c['fails'] : '✓' }}</td>
    </tr>
    @endforeach
    </tbody>
</x-data-table>

@if($openNc->count())
<h3 class="font-semibold text-slate-700 mb-3 mt-6">Open compliance non-conformities</h3>
<div class="rounded-xl bg-white border border-slate-200 divide-y divide-slate-100">
    @foreach($openNc as $nc)
    <a href="{{ route('quality.nonconformities.show', $nc) }}" class="flex items-center justify-between px-4 py-3 hover:bg-slate-50">
        <div><span class="text-xs font-mono text-slate-400">{{ $nc->reference }}</span><p class="text-sm font-medium text-slate-800">{{ $nc->title }}</p></div>
        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $sevColor[$nc->severity] ?? 'slate' }}-100 text-{{ $sevColor[$nc->severity] ?? 'slate' }}-700">{{ $nc->severity }}</span>
    </a>
    @endforeach
</div>
@endif

@endif

@endsection
