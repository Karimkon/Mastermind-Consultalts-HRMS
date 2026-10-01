@extends('layouts.app')
@section('title', 'Recruitment Analytics')
@section('content')

<x-page-header title="Recruitment Analytics" subtitle="Applications by position and by area"/>

<form method="GET" class="card p-4 mb-6 flex flex-wrap items-end gap-3">
    <div class="flex-1 min-w-[240px]">
        <label class="form-label">Position</label>
        <select name="job" class="form-input" onchange="this.form.submit()">
            <option value="">All positions ({{ $breakdown['total'] }} applications)</option>
            @foreach($jobs as $j)
            <option value="{{ $j->id }}" @selected($jobId === $j->id)>
                {{ $j->title }} — {{ $j->candidates_count }} {{ Str::plural('application', $j->candidates_count) }}
            </option>
            @endforeach
        </select>
    </div>
    @if($jobId)
    <a href="{{ route('recruitment.analytics') }}" class="btn-secondary">Clear</a>
    @endif
</form>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @foreach([
        ['Applications', $breakdown['total'], 'fa-file-lines', 'blue'],
        ['Placed on the map', $placed, 'fa-map-location-dot', 'emerald'],
        ['Awaiting lookup', $pending, 'fa-hourglass-half', 'amber'],
        ['No address recorded', $noAddress, 'fa-question', 'slate'],
    ] as [$label, $value, $icon, $colour])
    <div class="card p-4">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-{{ $colour }}-50 text-{{ $colour }}-600 flex items-center justify-center">
                <i class="fas {{ $icon }}"></i>
            </div>
            <div>
                <p class="text-xl font-bold text-slate-800 leading-none">{{ $value }}</p>
                <p class="text-xs text-slate-500 mt-1">{{ $label }}</p>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="grid lg:grid-cols-2 gap-6">

    {{-- Per position --}}
    <div class="card p-6">
        <h3 class="font-semibold text-slate-700 mb-1">Applications per position</h3>
        <p class="text-xs text-slate-400 mb-5">Share of the {{ $breakdown['total'] }} {{ Str::plural('application', $breakdown['total']) }} in view.</p>

        @forelse($breakdown['by_position'] as $row)
        <div class="mb-4">
            <div class="flex items-center justify-between text-sm mb-1.5">
                <span class="font-medium text-slate-700">{{ $row['label'] }}</span>
                <span class="text-slate-500"><strong class="text-slate-800">{{ $row['count'] }}</strong> &middot; {{ $row['percent'] }}%</span>
            </div>
            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full bg-blue-500 rounded-full" style="width: {{ min(100, max(2, $row['percent'])) }}%"></div>
            </div>
        </div>
        @empty
        <p class="text-sm text-slate-400 py-6 text-center">No applications yet.</p>
        @endforelse
    </div>

    {{-- Per area --}}
    <div class="card p-6">
        <h3 class="font-semibold text-slate-700 mb-1">Applications per area</h3>
        <p class="text-xs text-slate-400 mb-5">
            Worked out from the address each application arrived on. Approximate:
            a phone on mobile data can resolve to the carrier's gateway rather than the applicant's town.
        </p>

        @forelse($breakdown['by_area'] as $row)
        <div class="mb-4">
            <div class="flex items-center justify-between text-sm mb-1.5">
                <span class="font-medium text-slate-700">{{ $row['label'] }}</span>
                <span class="text-slate-500"><strong class="text-slate-800">{{ $row['count'] }}</strong> &middot; {{ $row['percent'] }}%</span>
            </div>
            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full {{ $row['label'] === 'Unknown area' ? 'bg-slate-300' : 'bg-emerald-500' }} rounded-full"
                     style="width: {{ min(100, max(2, $row['percent'])) }}%"></div>
            </div>
        </div>
        @empty
        <p class="text-sm text-slate-400 py-6 text-center">No applications yet.</p>
        @endforelse

        @if($pending > 0)
        <p class="text-xs text-amber-600 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2 mt-4">
            <i class="fas fa-hourglass-half mr-1"></i>
            {{ $pending }} {{ Str::plural('application', $pending) }} still waiting to be placed.
            This runs automatically every half hour.
        </p>
        @endif
    </div>

    {{-- Towns --}}
    <div class="card p-6">
        <h3 class="font-semibold text-slate-700 mb-1">Towns and cities</h3>
        <p class="text-xs text-slate-400 mb-4">The fifteen most common.</p>
        @forelse($cities as $city)
        <div class="flex items-center justify-between py-2 border-b border-slate-50 last:border-0">
            <span class="text-sm text-slate-700">
                {{ $city['label'] }}
                @if($city['country'] && $city['country'] !== 'Uganda')
                <span class="text-xs text-slate-400">({{ $city['country'] }})</span>
                @endif
            </span>
            <span class="text-sm font-semibold text-slate-800">{{ $city['count'] }}</span>
        </div>
        @empty
        <p class="text-sm text-slate-400 py-6 text-center">Nothing placed to a town yet.</p>
        @endforelse
    </div>

    {{-- Pipeline --}}
    <div class="card p-6">
        <h3 class="font-semibold text-slate-700 mb-1">Where applications stand</h3>
        <p class="text-xs text-slate-400 mb-4">Current stage of every application in view.</p>
        @php
        $stageLabels = [
            'new' => 'Applied', 'screening' => 'Screening', 'shortlisted' => 'Shortlisted',
            'interview' => 'Interview', 'offer' => 'Offer made', 'hired' => 'Hired', 'rejected' => 'Not taken forward',
        ];
        $stageColours = [
            'new' => 'slate', 'screening' => 'blue', 'shortlisted' => 'indigo',
            'interview' => 'violet', 'offer' => 'amber', 'hired' => 'emerald', 'rejected' => 'rose',
        ];
        @endphp
        @foreach($stageLabels as $key => $label)
        @php $count = $byStage[$key] ?? 0; @endphp
        <div class="flex items-center justify-between py-2 border-b border-slate-50 last:border-0">
            <span class="text-sm text-slate-700 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-{{ $stageColours[$key] }}-500"></span>{{ $label }}
            </span>
            <span class="text-sm font-semibold {{ $count ? 'text-slate-800' : 'text-slate-300' }}">{{ $count }}</span>
        </div>
        @endforeach
    </div>
</div>

@endsection
