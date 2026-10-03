@extends('layouts.app')
@section('title', 'Quality Management')
@section('content')

@php
    $scoreColor = fn ($s) => $s === null ? 'slate' : ($s >= 90 ? 'emerald' : ($s >= 75 ? 'amber' : 'red'));
    $sevColor = ['critical' => 'red', 'high' => 'orange', 'medium' => 'amber', 'low' => 'slate'];
@endphp

<x-page-header title="Quality Management" subtitle="The control layer over the entire HRMS">
    <a href="{{ route('quality.nonconformities.index') }}" class="btn-secondary"><i class="fas fa-triangle-exclamation mr-1"></i> Non-conformities</a>
    <form action="{{ route('quality.checks.run') }}" method="POST" class="inline">
        @csrf
        <input type="hidden" name="scope" value="all">
        <button type="submit" class="btn-primary"><i class="fas fa-play mr-1"></i> Run quality scan</button>
    </form>
</x-page-header>

@if(session('success'))
<div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

@if(! $latest)
<div class="rounded-xl border-2 border-dashed border-slate-200 bg-white p-10 text-center">
    <i class="fas fa-shield-halved text-4xl text-slate-300 mb-3"></i>
    <h3 class="text-lg font-semibold text-slate-700">No quality scan yet</h3>
    <p class="text-slate-500 text-sm mt-1 mb-4">Run the engine to measure your live HR data against the quality standards.</p>
    <form action="{{ route('quality.checks.run') }}" method="POST">@csrf<input type="hidden" name="scope" value="all">
        <button class="btn-primary"><i class="fas fa-play mr-1"></i> Run the first scan</button>
    </form>
</div>
@else

{{-- ===== Top row: overall score + non-conformity counts ===== --}}
<div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-6">
    @php $c = $scoreColor($overall); @endphp
    <div class="rounded-xl bg-white border border-slate-200 p-5 flex flex-col justify-between">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Overall quality score</p>
        <div class="mt-2 flex items-end gap-2">
            <span class="text-5xl font-bold text-{{ $c }}-600">{{ $overall !== null ? rtrim(rtrim(number_format($overall,1),'0'),'.') : '-' }}</span>
            <span class="text-2xl font-semibold text-{{ $c }}-400 mb-1">%</span>
        </div>
        <div class="mt-3 h-2 rounded-full bg-slate-100 overflow-hidden">
            <div class="h-full bg-{{ $c }}-500" style="width: {{ max(0,min(100,$overall ?? 0)) }}%"></div>
        </div>
        <p class="text-xs text-slate-400 mt-2">Last scan {{ $latest->completed_at?->diffForHumans() }}</p>
    </div>

    <div class="rounded-xl bg-white border border-slate-200 p-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Open non-conformities</p>
        <p class="text-4xl font-bold text-slate-800 mt-2">{{ $openTotal }}</p>
        <div class="flex flex-wrap gap-1.5 mt-3">
            @foreach(['critical','high','medium','low'] as $sev)
            @if(($ncBySeverity[$sev] ?? 0) > 0)
            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $sevColor[$sev] }}-100 text-{{ $sevColor[$sev] }}-700">{{ $ncBySeverity[$sev] }} {{ $sev }}</span>
            @endif
            @endforeach
        </div>
        <a href="{{ route('quality.nonconformities.index') }}" class="text-xs text-emerald-600 font-medium mt-3 inline-block">View backlog &rarr;</a>
    </div>

    <div class="rounded-xl bg-white border border-slate-200 p-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Records scanned</p>
        <p class="text-4xl font-bold text-slate-800 mt-2">{{ number_format($latest->subjects_scanned) }}</p>
        <p class="text-sm text-slate-500 mt-3">{{ $latest->checks_run }} checks &middot; <span class="text-red-600 font-medium">{{ number_format($latest->failed) }} issues</span></p>
    </div>

    <div class="rounded-xl bg-white border border-slate-200 p-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Warnings</p>
        <p class="text-4xl font-bold text-slate-800 mt-2">{{ number_format($latest->warnings) }}</p>
        <a href="{{ route('quality.checks.results', ['run' => $latest->id, 'status' => 'warning']) }}" class="text-xs text-emerald-600 font-medium mt-3 inline-block">Review warnings &rarr;</a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- ===== Quality by HR function ===== --}}
    <div class="lg:col-span-2">
        <h3 class="font-semibold text-slate-700 mb-3">Quality by HR function</h3>
        <div class="rounded-xl bg-white border border-slate-200 divide-y divide-slate-100">
            @forelse($functions as $f)
            @php $fc = $scoreColor($f['score']); @endphp
            <div class="flex items-center gap-4 px-5 py-4">
                <div class="w-40 shrink-0">
                    <p class="font-medium text-slate-800">{{ $f['label'] }}</p>
                    <p class="text-xs text-slate-400">{{ $f['checks'] }} checks &middot; {{ $f['failed'] }} failing</p>
                </div>
                <div class="flex-1 h-2.5 rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-full bg-{{ $fc }}-500" style="width: {{ max(2,min(100,$f['score'])) }}%"></div>
                </div>
                <span class="w-14 text-right font-semibold text-{{ $fc }}-600">{{ rtrim(rtrim(number_format($f['score'],1),'0'),'.') }}%</span>
            </div>
            @empty
            <p class="px-5 py-6 text-sm text-slate-400">No function data.</p>
            @endforelse
        </div>

        {{-- ===== Top issues ===== --}}
        <h3 class="font-semibold text-slate-700 mb-3 mt-6">Biggest issues in the last scan</h3>
        <x-data-table>
            <thead><tr class="table-header"><th>Check</th><th>Function</th><th>Severity</th><th class="text-right">Records</th></tr></thead>
            <tbody>
            @forelse($topIssues as $i)
            <tr class="table-row">
                <td class="px-4 py-3 font-medium text-slate-800">{{ $i['name'] }}</td>
                <td class="px-4 py-3 text-slate-500 text-sm">{{ ucwords(str_replace('_',' ', $i['function'])) }}</td>
                <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $sevColor[$i['severity']] ?? 'slate' }}-100 text-{{ $sevColor[$i['severity']] ?? 'slate' }}-700">{{ $i['severity'] }}</span></td>
                <td class="px-4 py-3 text-right font-semibold text-red-600">{{ $i['fails'] }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="px-4 py-6 text-center text-sm text-emerald-600"><i class="fas fa-check-circle mr-1"></i> No failing checks - everything conforms.</td></tr>
            @endforelse
            </tbody>
        </x-data-table>
    </div>

    {{-- ===== Right rail ===== --}}
    <div class="space-y-6">
        <div>
            <h3 class="font-semibold text-slate-700 mb-3">Lowest-scoring departments</h3>
            <div class="rounded-xl bg-white border border-slate-200 divide-y divide-slate-100">
                @forelse(array_slice($departments, 0, 8) as $d)
                @php $dc = $scoreColor($d['score']); @endphp
                <div class="flex items-center justify-between px-4 py-3">
                    <div>
                        <p class="font-medium text-slate-800 text-sm">{{ $d['name'] }}</p>
                        <p class="text-xs text-slate-400">{{ $d['flagged'] }}/{{ $d['total'] }} flagged</p>
                    </div>
                    <span class="font-semibold text-{{ $dc }}-600">{{ rtrim(rtrim(number_format($d['score'],1),'0'),'.') }}%</span>
                </div>
                @empty
                <p class="px-4 py-6 text-sm text-slate-400">No department data.</p>
                @endforelse
            </div>
        </div>

        <div>
            <h3 class="font-semibold text-slate-700 mb-3">Open non-conformities</h3>
            <div class="rounded-xl bg-white border border-slate-200 divide-y divide-slate-100">
                @forelse($recentNc as $nc)
                <a href="{{ route('quality.nonconformities.show', $nc) }}" class="block px-4 py-3 hover:bg-slate-50">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-mono text-slate-400">{{ $nc->reference }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-{{ $sevColor[$nc->severity] ?? 'slate' }}-100 text-{{ $sevColor[$nc->severity] ?? 'slate' }}-700">{{ $nc->severity }}</span>
                    </div>
                    <p class="text-sm font-medium text-slate-800 mt-0.5">{{ $nc->title }}</p>
                </a>
                @empty
                <p class="px-4 py-6 text-sm text-emerald-600"><i class="fas fa-check-circle mr-1"></i> No open non-conformities.</p>
                @endforelse
            </div>
        </div>

        <div>
            <h3 class="font-semibold text-slate-700 mb-3">Recent scans</h3>
            <div class="rounded-xl bg-white border border-slate-200 divide-y divide-slate-100">
                @foreach($recentRuns as $r)
                @php $rc = $scoreColor($r->score); @endphp
                <a href="{{ route('quality.checks.results', ['run' => $r->id]) }}" class="flex items-center justify-between px-4 py-2.5 hover:bg-slate-50 text-sm">
                    <span class="text-slate-500">{{ $r->completed_at?->format('d M, H:i') }}</span>
                    <span class="font-semibold text-{{ $rc }}-600">{{ $r->score !== null ? rtrim(rtrim(number_format($r->score,1),'0'),'.').'%' : '-' }}</span>
                </a>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endif

@endsection
