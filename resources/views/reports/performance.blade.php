@extends('layouts.app')
@section('title', 'Performance Report')
@section('content')
<x-page-header title="Performance Report" subtitle="Review scores by cycle and department"/>

<x-filter-bar :action="route('reports.performance')">
    <div class="w-52"><select name="cycle_id" class="form-input w-full select2"><option value="">All Cycles</option>@foreach($cycles as $c)<option value="{{ $c->id }}" @selected(request('cycle_id')==$c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="w-48"><select name="department_id" class="form-input w-full select2"><option value="">All Departments</option>@foreach($departments as $d)<option value="{{ $d->id }}" @selected(request('department_id')==$d->id)>{{ $d->name }}</option>@endforeach</select></div>
</x-filter-bar>

<x-data-table>
    <thead><tr class="table-header"><th>Employee</th><th>Department</th><th>Cycle</th><th>Review Type</th><th>Score</th><th>Rating</th></tr></thead>
    <tbody>
    @forelse($reviews as $rv)
    @php
        $grade = $rv->total_score >= 90 ? 'Excellent' : ($rv->total_score >= 75 ? 'Good' : ($rv->total_score >= 60 ? 'Average' : 'Below Average'));
        $gradeColor = $rv->total_score >= 90 ? 'badge-green' : ($rv->total_score >= 75 ? 'badge-blue' : ($rv->total_score >= 60 ? 'badge-yellow' : 'badge-red'));
    @endphp
    <tr class="table-row">
        <td class="px-4 py-3">
            <div class="flex items-center gap-2">
                <img src="{{ $rv->employee->avatar_url }}" class="w-7 h-7 rounded-full">
                <span class="text-sm font-medium text-slate-800">{{ $rv->employee->full_name }}</span>
            </div>
        </td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $rv->employee->department->name ?? '—' }}</td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $rv->cycle->name ?? '—' }}</td>
        <td class="px-4 py-3"><span class="badge badge-slate">{{ ucfirst($rv->type) }}</span></td>
        <td class="px-4 py-3 text-sm font-bold text-slate-800">{{ $rv->total_score }}</td>
        <td class="px-4 py-3"><span class="badge {{ $gradeColor }}">{{ $grade }}</span></td>
    </tr>
    @empty
    <tr><td colspan="6" class="text-center py-8 text-slate-400">No performance data found.</td></tr>
    @endforelse
    </tbody>
</x-data-table>
<div class="mt-4">{{ $reviews->withQueryString()->links() }}</div>
@endsection