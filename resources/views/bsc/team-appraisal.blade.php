@extends('layouts.app')
@section('title', 'Team BSC Appraisals')
@section('content')
<x-page-header title="Team BSC Appraisals">
    @if($cycle)
    <span class="text-sm text-slate-500">{{ $cycle->name }} &bull; {{ $cycle->period }} {{ $cycle->year }}</span>
    {!! $cycle->status_badge !!}
    @endif
</x-page-header>

@if(!$cycle)
<div class="card p-12 text-center text-slate-400">
    <i class="fas fa-chart-bar text-5xl mb-4"></i>
    <p class="font-semibold text-lg">No Active BSC Cycle</p>
    <p class="text-sm">Activate a BSC cycle first to view team appraisals.</p>
    @can('bsc.manage')
    <a href="{{ route('bsc.index') }}" class="btn-primary mt-4">Manage Cycles</a>
    @endcan
</div>
@elseif($team->isEmpty())
<div class="card p-12 text-center text-slate-400">
    <i class="fas fa-users text-5xl mb-4"></i>
    <p class="font-semibold text-lg">No Team Members</p>
    <p class="text-sm">You have no subordinate employees to appraise.</p>
</div>
@else

{{-- Status Filter --}}
<div class="flex gap-2 mb-4 flex-wrap">
    @foreach([null => 'All', 'draft' => 'Draft', 'submitted' => 'Submitted', 'reviewed' => 'Reviewed', 'approved' => 'Approved'] as $val => $label)
    <a href="{{ route('bsc.team-appraisal', array_filter(['status' => $val])) }}"
       class="px-3 py-1.5 text-sm rounded-lg font-medium transition-colors {{ ($statusFilter ?? '') == $val ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
        {{ $label }}
    </a>
    @endforeach
</div>

<div class="grid grid-cols-1 gap-4">
    @foreach($team as $item)
    @php $emp = $item['employee']; @endphp
    <div class="card p-5">
        <div class="flex items-center gap-4">
            <img src="{{ $emp->avatar_url }}" class="w-12 h-12 rounded-xl object-cover border border-slate-200">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-3 flex-wrap">
                    <a href="{{ route('bsc.team-appraisal') }}?employee={{ $emp->id }}" class="font-bold text-slate-900 hover:text-blue-600">
                        {{ $emp->full_name }}
                    </a>
                    {!! $emp->status_badge !!}
                </div>
                <p class="text-xs text-slate-500">{{ $emp->designation?->title }} &bull; {{ $emp->department?->name }}</p>
            </div>
            <div class="flex items-center gap-6 text-center">
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wide">Score</p>
                    <p class="text-xl font-black {{ $item['overall_score'] >= 3 ? 'text-green-600' : ($item['overall_score'] >= 2 ? 'text-amber-600' : 'text-red-500') }}">
                        {{ $item['overall_score'] }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wide">Entries</p>
                    <p class="text-lg font-bold text-slate-700">{{ $item['entry_count'] }}/{{ $item['total_kras'] }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wide">Approved</p>
                    <p class="text-lg font-bold text-emerald-600">{{ $item['approved_count'] }}</p>
                </div>
            </div>
        </div>

        {{-- Progress Bar --}}
        <div class="mt-3">
            <div class="flex justify-between text-xs text-slate-500 mb-1">
                <span>Completion progress</span>
                <span>{{ $item['progress'] }}%</span>
            </div>
            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all {{ $item['progress'] >= 80 ? 'bg-green-400' : ($item['progress'] >= 50 ? 'bg-amber-400' : 'bg-red-400') }}"
                     style="width: {{ $item['progress'] }}%"></div>
            </div>
        </div>

        {{-- Entry Status Pills --}}
        <div class="flex gap-2 mt-3 flex-wrap">
            @if($item['submitted_count'])
            <span class="badge-blue text-xs">{{ $item['submitted_count'] }} Submitted</span>
            @endif
            @if($item['approved_count'])
            <span class="badge-green text-xs">{{ $item['approved_count'] }} Approved</span>
            @endif
            @if($item['entry_count'] - $item['submitted_count'] - $item['approved_count'] > 0)
            <span class="badge-yellow text-xs">{{ $item['entry_count'] - $item['submitted_count'] - $item['approved_count'] }} Draft</span>
            @endif
        </div>

        {{-- Entries Detail --}}
        @if($item['entries']->count())
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="bg-slate-50 text-slate-400 uppercase">
                        <th class="px-3 py-1.5 text-left">KRA</th>
                        <th class="px-3 py-1.5 text-center">Rating</th>
                        <th class="px-3 py-1.5 text-center">Weighted</th>
                        <th class="px-3 py-1.5 text-center">Status</th>
                        <th class="px-3 py-1.5 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($item['entries']->take(5) as $entry)
                    <tr class="hover:bg-slate-50">
                        <td class="px-3 py-2 font-medium text-slate-700">{{ $entry->kra?->kra_name }}</td>
                        <td class="px-3 py-2 text-center">
                            @if($entry->rating)
                            <div class="flex items-center justify-center gap-0.5">
                                @for($i=1;$i<=5;$i++)<i class="fas fa-star {{ $i <= $entry->rating ? 'text-amber-400' : 'text-slate-200' }}"></i>@endfor
                            </div>
                            @else
                            <span class="text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-center font-semibold text-slate-600">
                            {{ $entry->weighted_index !== null ? number_format($entry->weighted_index, 4) : '—' }}
                        </td>
                        <td class="px-3 py-2 text-center">{!! $entry->status_badge !!}</td>
                        <td class="px-3 py-2 text-center">
                            <a href="{{ route('bsc.entries.show', $entry) }}" class="text-blue-500 hover:text-blue-700 font-semibold">
                                <i class="fas fa-eye mr-0.5"></i> View
                            </a>
                            @if($entry->status === 'submitted')
                            <form method="POST" action="{{ route('bsc.entries.approve', $entry) }}" class="inline ml-2">
                                @csrf
                                <button class="text-green-600 hover:text-green-800 font-semibold"><i class="fas fa-check mr-0.5"></i> Approve</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
    @endforeach
</div>
@endif
@endsection
