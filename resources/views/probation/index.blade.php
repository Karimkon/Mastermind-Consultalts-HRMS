@extends('layouts.app')
@section('title', 'Probation Tracking')
@section('content')
<x-page-header title="Probation Tracking">
    <span class="text-sm text-slate-500">Monitor employee probation periods and outcomes</span>
</x-page-header>

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="card p-4 border-t-4 border-amber-400">
        <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">On Probation</p>
        <p class="text-3xl font-black text-amber-600">{{ $stats['onProbation'] }}</p>
    </div>
    <div class="card p-4 border-t-4 border-green-400">
        <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Passed</p>
        <p class="text-3xl font-black text-green-600">{{ $stats['passed'] }}</p>
    </div>
    <div class="card p-4 border-t-4 border-blue-400">
        <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Due This Month</p>
        <p class="text-3xl font-black text-blue-600">{{ $stats['dueThisMonth'] }}</p>
    </div>
    <div class="card p-4 border-t-4 border-red-400">
        <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Overdue Review</p>
        <p class="text-3xl font-black text-red-600">{{ $stats['overdue'] }}</p>
    </div>
</div>

{{-- Filter Tabs --}}
<div class="flex gap-2 mb-4 flex-wrap">
    @foreach(['' => 'All', 'on_probation' => 'On Probation', 'passed' => 'Passed', 'failed' => 'Failed', 'extended' => 'Extended', 'overdue' => 'Overdue'] as $val => $label)
    <a href="{{ route('probation.index', $val ? ['status' => $val] : []) }}"
       class="px-3 py-1.5 text-sm rounded-lg font-medium transition-colors
              {{ ($statusFilter ?? '') === $val ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
        {{ $label }}
        @if($val === 'overdue' && $stats['overdue'] > 0)
        <span class="ml-1 bg-red-500 text-white text-xs rounded-full px-1.5">{{ $stats['overdue'] }}</span>
        @endif
    </a>
    @endforeach
</div>

{{-- Table --}}
<div class="card">
    @if($employees->count())
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wider">
                    <th class="px-4 py-3 text-left">Employee</th>
                    <th class="px-4 py-3 text-center">Hire Date</th>
                    <th class="px-4 py-3 text-center">Probation End</th>
                    <th class="px-4 py-3 text-center">Days Left</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @foreach($employees as $emp)
                @php
                $isOverdue = $emp->probation_end_date && $emp->probation_end_date->isPast()
                             && in_array($emp->probation_status, ['on_probation', null]);
                $daysLeft  = $emp->probation_end_date ? now()->diffInDays($emp->probation_end_date, false) : null;
                @endphp
                <tr class="hover:bg-slate-50 {{ $isOverdue ? 'bg-red-50' : '' }}">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <img src="{{ $emp->avatar_url }}" class="w-9 h-9 rounded-lg object-cover border border-slate-200">
                            <div>
                                <a href="{{ route('probation.show', $emp) }}" class="font-semibold text-slate-800 hover:text-blue-600">{{ $emp->full_name }}</a>
                                <p class="text-xs text-slate-400">{{ $emp->designation?->title }} &bull; {{ $emp->department?->name }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-center text-slate-600">{{ $emp->hire_date->format('d M Y') }}</td>
                    <td class="px-4 py-3 text-center text-slate-600">
                        {{ $emp->probation_end_date ? $emp->probation_end_date->format('d M Y') : '—' }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($daysLeft !== null)
                        @if($isOverdue)
                        <span class="text-red-600 font-bold text-xs">{{ abs((int)$daysLeft) }} days overdue</span>
                        @elseif($daysLeft <= 7)
                        <span class="text-amber-600 font-bold text-xs">{{ (int)$daysLeft }} days</span>
                        @elseif($daysLeft <= 30)
                        <span class="text-blue-600 font-semibold text-xs">{{ (int)$daysLeft }} days</span>
                        @else
                        <span class="text-slate-500 text-xs">{{ (int)$daysLeft }} days</span>
                        @endif
                        @else
                        <span class="text-slate-300">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($isOverdue)
                        <span class="badge-red text-xs animate-pulse">Overdue</span>
                        @elseif($emp->probation_status)
                        {!! $emp->probation_status_badge !!}
                        @else
                        <span class="badge-gray text-xs">Not Set</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <a href="{{ route('probation.show', $emp) }}" class="text-blue-500 hover:text-blue-700 text-xs font-semibold">
                                <i class="fas fa-eye mr-0.5"></i> Review
                            </a>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="px-5 py-4 border-t border-slate-100">{{ $employees->withQueryString()->links() }}</div>
    @else
    <div class="py-16 text-center text-slate-400">
        <i class="fas fa-user-clock text-5xl mb-4"></i>
        <p class="font-semibold text-lg">No employees found</p>
        <p class="text-sm">No employees match the selected filter.</p>
    </div>
    @endif
</div>
@endsection
