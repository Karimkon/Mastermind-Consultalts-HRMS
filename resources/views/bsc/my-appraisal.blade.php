@extends('layouts.app')
@section('title', 'My BSC Appraisal')
@section('content')
<x-page-header title="My BSC Appraisal">
    @if($cycle)
    <span class="text-sm text-slate-500">{{ $cycle->name }} &bull; {{ $cycle->period }} {{ $cycle->year }}</span>
    @endif
</x-page-header>

@if(!$cycle)
<div class="card p-12 text-center text-slate-400">
    <i class="fas fa-chart-bar text-5xl mb-4"></i>
    <p class="font-semibold text-lg">No Active BSC Cycle</p>
    <p class="text-sm">There is no active appraisal cycle at the moment. Check back later.</p>
</div>
@elseif(empty($perspectives) || collect($perspectives)->every(fn($p) => empty($p['kras'])))
<div class="card p-12 text-center text-slate-400">
    <i class="fas fa-clipboard-list text-5xl mb-4"></i>
    <p class="font-semibold text-lg">No Appraisal Entries Yet</p>
    <p class="text-sm">Your appraisal entries have not been initialized. Contact your manager or HR.</p>
</div>
@else

@if(session('success'))
<div class="alert-success mb-4">{{ session('success') }}</div>
@endif

{{-- Overall Score Card --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="card p-4 border-t-4 border-blue-500 text-center">
        <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Overall Score</p>
        <p class="text-3xl font-black text-blue-700">{{ number_format($overallScore, 2) }}</p>
        <p class="text-xs text-slate-400">Weighted Index</p>
    </div>
    @php
    $allEntries = collect($perspectives)->flatMap(fn($p) => collect($p['kras'])->map(fn($k) => $k['entry'])->filter());
    $rated = $allEntries->whereNotNull('rating');
    @endphp
    <div class="card p-4 text-center">
        <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">KRAs Rated</p>
        <p class="text-3xl font-black text-slate-700">{{ $rated->count() }} / {{ $allEntries->count() }}</p>
        <p class="text-xs text-slate-400">Completed</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Submitted</p>
        <p class="text-3xl font-black text-green-600">{{ $allEntries->where('status','submitted')->count() + $allEntries->where('status','approved')->count() }}</p>
        <p class="text-xs text-slate-400">of {{ $allEntries->count() }}</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Approved</p>
        <p class="text-3xl font-black text-emerald-600">{{ $allEntries->where('status','approved')->count() }}</p>
        <p class="text-xs text-slate-400">Finalized</p>
    </div>
</div>

{{-- Perspectives --}}
@php $colorMap = ['blue'=>'blue','green'=>'green','purple'=>'purple','orange'=>'amber']; @endphp
@foreach($perspectives as $key => $p)
@if(!empty($p['kras']))
@php $c = $colorMap[$p['color']] ?? 'slate'; @endphp
<div class="card mb-5">
    <div class="px-5 py-3 bg-{{ $c }}-50 border-b border-{{ $c }}-100 flex items-center justify-between">
        <div>
            <h4 class="font-bold text-{{ $c }}-800">{{ $p['label'] }}</h4>
            <p class="text-xs text-{{ $c }}-600">Perspective Weight: {{ $p['weight'] }}%</p>
        </div>
        @php $perspScore = collect($p['kras'])->sum(fn($k) => $k['entry']?->weighted_index ?? 0); @endphp
        <div class="text-right">
            <p class="text-xs text-{{ $c }}-500 uppercase">Score</p>
            <p class="text-xl font-black text-{{ $c }}-700">{{ number_format($perspScore, 2) }}</p>
        </div>
    </div>
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wider">
                <th class="px-4 py-2 text-left">KRA / Objective</th>
                <th class="px-4 py-2 text-center">Target</th>
                <th class="px-4 py-2 text-center">Actual</th>
                <th class="px-4 py-2 text-center">% of Target</th>
                <th class="px-4 py-2 text-center">Rating</th>
                <th class="px-4 py-2 text-center">Weighted</th>
                <th class="px-4 py-2 text-center">Status</th>
                <th class="px-4 py-2 text-center">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @foreach($p['kras'] as $item)
            @php $kra = $item['kra']; $entry = $item['entry']; @endphp
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3">
                    <p class="font-semibold text-slate-800 text-sm">{{ $kra->kra_name }}</p>
                    @if($kra->objective)<p class="text-xs text-slate-400">{{ $kra->objective }}</p>@endif
                </td>
                <td class="px-4 py-3 text-center text-slate-600">{{ number_format($kra->target, 0) }} {{ $kra->unit }}</td>
                <td class="px-4 py-3 text-center font-semibold">
                    {{ $entry?->actual_achieved !== null ? number_format($entry->actual_achieved, 1) . ' ' . $kra->unit : '—' }}
                </td>
                <td class="px-4 py-3 text-center">
                    @if($entry?->target_percent !== null)
                    <span class="text-xs font-semibold {{ $entry->target_percent >= 100 ? 'text-green-600' : ($entry->target_percent >= 80 ? 'text-amber-600' : 'text-red-500') }}">
                        {{ number_format($entry->target_percent, 1) }}%
                    </span>
                    @else
                    <span class="text-slate-300">—</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-center">
                    @if($entry?->rating)
                    <div class="flex items-center justify-center gap-0.5">
                        @for($i = 1; $i <= 5; $i++)
                        <i class="fas fa-star text-xs {{ $i <= $entry->rating ? 'text-amber-400' : 'text-slate-200' }}"></i>
                        @endfor
                        <span class="ml-1 text-xs font-bold text-slate-600">({{ $entry->rating }})</span>
                    </div>
                    @else
                    <span class="text-slate-300 text-xs">Not rated</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-center font-semibold text-{{ $c }}-600">
                    {{ $entry?->weighted_index !== null ? number_format($entry->weighted_index, 4) : '—' }}
                </td>
                <td class="px-4 py-3 text-center">
                    @if($entry)
                    {!! $entry->status_badge !!}
                    @else
                    <span class="badge-gray text-xs">No entry</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-center">
                    @if($entry)
                    <a href="{{ route('bsc.entries.edit', $entry) }}" class="text-blue-500 hover:text-blue-700 text-xs font-semibold">
                        <i class="fas fa-edit mr-0.5"></i> Edit
                    </a>
                    @if($entry->status === 'draft' && $entry->rating)
                    <form method="POST" action="{{ route('bsc.entries.submit', $entry) }}" class="inline ml-2">
                        @csrf
                        <button class="text-green-600 hover:text-green-800 text-xs font-semibold">
                            <i class="fas fa-paper-plane mr-0.5"></i> Submit
                        </button>
                    </form>
                    @endif
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endforeach
@endif
@endsection
