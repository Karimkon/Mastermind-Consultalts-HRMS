@extends('layouts.app')
@section('title', 'Training Plan ' . $year)

@section('content')
<div class="flex items-start justify-between flex-wrap gap-3 mb-4">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Annual Training Plan — {{ $year }}</h1>
        <p class="text-sm text-slate-500">
            Who is trained, when, where, by whom and at what cost.
        </p>
    </div>
    <div class="flex items-center gap-2">
        <form method="GET" class="flex items-center gap-2">
            <select name="year" class="form-input text-sm" onchange="this.form.submit()">
                @foreach($years as $y)
                    <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                @endforeach
            </select>
        </form>
        @if($canInitiate)
        <a href="{{ route('training.plan.create') }}" class="btn-primary text-sm">
            <i class="fas fa-plus mr-1"></i> Add training
        </a>
        @endif
    </div>
</div>

@include('admin.blog.partials.flash')

{{-- The year at a glance. Committed spend is kept apart from proposed: only the
     CEO-approved figure is money the company has actually agreed to. --}}
<div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
    @foreach([
        ['Sessions',        number_format($totals['sessions']),        'fa-calendar-days', 'text-slate-700'],
        ['Seats planned',   number_format($totals['seats']),           'fa-users',         'text-slate-700'],
        ['Awaiting sign-off', number_format($totals['awaiting']),      'fa-hourglass-half','text-amber-600'],
        ['Proposed cost',   number_format($totals['cost']),            'fa-file-invoice',  'text-slate-700'],
        ['Committed cost',  number_format($totals['approvedCost']),    'fa-circle-check',  'text-emerald-700'],
    ] as [$label, $value, $icon, $tone])
    <div class="card p-3">
        <p class="text-[11px] uppercase tracking-wide text-slate-400">{{ $label }}</p>
        <p class="text-lg font-bold {{ $tone }} mt-0.5">
            <i class="fas {{ $icon }} text-xs text-slate-300 mr-1"></i>{{ $value }}
        </p>
    </div>
    @endforeach
</div>

@forelse($byMonth as $key => $monthSessions)
    <h2 class="text-sm font-semibold text-slate-600 mt-5 mb-2">
        {{ $key === 'unscheduled'
            ? 'Not yet scheduled'
            : \Carbon\Carbon::createFromFormat('Y-m', $key)->format('F Y') }}
        <span class="text-slate-400 font-normal">({{ $monthSessions->count() }})</span>
    </h2>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm" style="min-width:900px">
            <thead>
                <tr class="bg-slate-50 text-slate-600">
                    <th class="px-3 py-2 text-left">Training</th>
                    <th class="px-3 py-2 text-left w-40">Dates</th>
                    <th class="px-3 py-2 text-left w-32">Duration</th>
                    <th class="px-3 py-2 text-left w-44">Trainer / Venue</th>
                    <th class="px-3 py-2 text-center w-20">Seats</th>
                    <th class="px-3 py-2 text-right w-28">Cost</th>
                    <th class="px-3 py-2 text-center w-32">Status</th>
                </tr>
            </thead>
            <tbody>
            @foreach($monthSessions as $s)
                <tr class="border-t border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">
                        <a href="{{ route('training.plan.show', $s) }}"
                           class="font-medium text-slate-800 hover:text-blue-600">{{ $s->title }}</a>
                        <p class="text-xs text-slate-500">
                            {{ $s->category ?: 'Uncategorised' }} ·
                            {{ ucwords(str_replace('_', ' ', $s->delivery)) }}
                        </p>
                    </td>
                    <td class="px-3 py-2 text-slate-600">
                        @if($s->starts_on)
                            {{ $s->starts_on->format('d M') }}
                            @if($s->ends_on && !$s->ends_on->isSameDay($s->starts_on))
                                – {{ $s->ends_on->format('d M') }}
                            @endif
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-slate-600 text-xs">
                        @if($s->duration_days){{ rtrim(rtrim(number_format($s->duration_days,1),'0'),'.') }}d @endif
                        @if($s->duration_hours){{ rtrim(rtrim(number_format($s->duration_hours,1),'0'),'.') }}h @endif
                        @if(!$s->duration_days && !$s->duration_hours)<span class="text-slate-400">—</span>@endif
                    </td>
                    <td class="px-3 py-2 text-xs text-slate-600">
                        {{ $s->trainer ?: $s->provider ?: '—' }}
                        @if($s->venue)<p class="text-slate-400">{{ $s->venue }}</p>@endif
                    </td>
                    <td class="px-3 py-2 text-center text-slate-700">
                        {{ $s->participants->count() }}@if($s->max_participants)<span class="text-slate-400">/{{ $s->max_participants }}</span>@endif
                    </td>
                    <td class="px-3 py-2 text-right text-slate-700">{{ number_format($s->totalCost()) }}</td>
                    <td class="px-3 py-2 text-center">
                        <span class="badge-{{ $s->statusBadge() }}">{{ $s->statusLabel() }}</span>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@empty
    <div class="card p-10 text-center text-slate-400">
        <i class="fas fa-calendar-days text-4xl mb-3"></i>
        <p class="font-medium text-slate-600">Nothing planned for {{ $year }} yet.</p>
        @if($canInitiate)
        <p class="text-sm mt-1">
            <a href="{{ route('training.plan.create') }}" class="text-blue-600 hover:underline">
                Add the first training
            </a>
        </p>
        @endif
    </div>
@endforelse
@endsection
