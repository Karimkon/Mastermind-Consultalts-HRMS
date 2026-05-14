@extends('layouts.app')
@section('title', 'Balanced Scorecard')
@section('content')
<x-page-header title="Balanced Scorecard (BSC)">
    @can('bsc.manage')
    <a href="{{ route('bsc.cycles.create') }}" class="btn-primary"><i class="fas fa-plus mr-1"></i> New BSC Cycle</a>
    @endcan
    <a href="{{ route('bsc.my-appraisal') }}" class="btn-secondary"><i class="fas fa-user-check mr-1"></i> My Appraisal</a>
    @if(auth()->user()->hasAnyRole(['super-admin','hr-admin','manager','account-manager']))
    <a href="{{ route('bsc.team-appraisal') }}" class="btn-secondary"><i class="fas fa-users mr-1"></i> Team Appraisals</a>
    @endif
</x-page-header>

{{-- Info Cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @php
    $weights = [
        ['label'=>'Financials',               'pct'=>'15%', 'color'=>'blue',   'icon'=>'fa-chart-line'],
        ['label'=>'Customer',                 'pct'=>'15%', 'color'=>'green',  'icon'=>'fa-handshake'],
        ['label'=>'Internal Business Process','pct'=>'40%', 'color'=>'purple', 'icon'=>'fa-cogs'],
        ['label'=>'Learning & Growth',        'pct'=>'30%', 'color'=>'orange', 'icon'=>'fa-graduation-cap'],
    ];
    @endphp
    @foreach($weights as $w)
    <div class="card p-4 border-t-4 border-{{ $w['color'] }}-400">
        <div class="flex items-center justify-between mb-2">
            <i class="fas {{ $w['icon'] }} text-{{ $w['color'] }}-400 text-xl"></i>
            <span class="text-2xl font-black text-{{ $w['color'] }}-600">{{ $w['pct'] }}</span>
        </div>
        <p class="text-sm font-semibold text-slate-700">{{ $w['label'] }}</p>
        <p class="text-xs text-slate-400">BSC Perspective Weight</p>
    </div>
    @endforeach
</div>

{{-- Cycles Table --}}
<div class="card">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <h3 class="font-bold text-slate-800">Appraisal Cycles</h3>
        <span class="text-xs text-slate-400">{{ $cycles->total() }} cycles total</span>
    </div>
    @if($cycles->count())
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wider">
                    <th class="px-5 py-3 text-left">Cycle Name</th>
                    <th class="px-5 py-3 text-center">Period</th>
                    <th class="px-5 py-3 text-center">Year</th>
                    <th class="px-5 py-3 text-center">Dates</th>
                    <th class="px-5 py-3 text-center">KRAs</th>
                    <th class="px-5 py-3 text-center">Status</th>
                    <th class="px-5 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @foreach($cycles as $cycle)
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-5 py-4">
                        <a href="{{ route('bsc.cycles.show', $cycle) }}" class="font-semibold text-blue-600 hover:text-blue-800">{{ $cycle->name }}</a>
                    </td>
                    <td class="px-5 py-4 text-center text-slate-600">{{ $cycle->period }}</td>
                    <td class="px-5 py-4 text-center text-slate-600">{{ $cycle->year }}</td>
                    <td class="px-5 py-4 text-center text-xs text-slate-500">
                        {{ $cycle->start_date->format('d M Y') }} — {{ $cycle->end_date->format('d M Y') }}
                    </td>
                    <td class="px-5 py-4 text-center">
                        <span class="bg-slate-100 text-slate-700 font-semibold px-2 py-0.5 rounded text-xs">{{ $cycle->kras_count }}</span>
                    </td>
                    <td class="px-5 py-4 text-center">{!! $cycle->status_badge !!}</td>
                    <td class="px-5 py-4">
                        <div class="flex items-center justify-center gap-2">
                            <a href="{{ route('bsc.cycles.show', $cycle) }}" class="text-blue-500 hover:text-blue-700 text-xs font-semibold">
                                <i class="fas fa-eye mr-0.5"></i> View
                            </a>
                            @can('bsc.manage')
                            @if($cycle->status === 'draft')
                            <form method="POST" action="{{ route('bsc.cycles.activate', $cycle) }}" class="inline">
                                @csrf
                                <button class="text-green-600 hover:text-green-800 text-xs font-semibold"><i class="fas fa-play mr-0.5"></i> Activate</button>
                            </form>
                            @elseif($cycle->status === 'active')
                            <form method="POST" action="{{ route('bsc.cycles.close', $cycle) }}" class="inline"
                                  onsubmit="return confirm('Close this BSC cycle? This cannot be undone.')">
                                @csrf
                                <button class="text-red-500 hover:text-red-700 text-xs font-semibold"><i class="fas fa-lock mr-0.5"></i> Close</button>
                            </form>
                            @endif
                            <a href="{{ route('bsc.cycles.edit', $cycle) }}" class="text-amber-500 hover:text-amber-700 text-xs font-semibold">
                                <i class="fas fa-edit mr-0.5"></i> Edit
                            </a>
                            @endcan
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="px-5 py-4 border-t border-slate-100">{{ $cycles->links() }}</div>
    @else
    <div class="py-16 text-center text-slate-400">
        <i class="fas fa-chart-bar text-5xl mb-4"></i>
        <p class="font-semibold text-lg">No BSC Cycles yet</p>
        <p class="text-sm mb-4">Create your first balanced scorecard appraisal cycle.</p>
        @can('bsc.manage')
        <a href="{{ route('bsc.cycles.create') }}" class="btn-primary">
            <i class="fas fa-plus mr-1"></i> Create First Cycle
        </a>
        @endcan
    </div>
    @endif
</div>
@endsection
