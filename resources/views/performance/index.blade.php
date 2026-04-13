@extends('layouts.app')
@section('title', 'Performance Reviews')
@section('content')
<x-page-header title="Performance Reviews" subtitle="Employee evaluations and BSC KPI tracking">
    @can('manage-performance')
    <a href="{{ route('performance.cycles.create') }}" class="btn-secondary"><i class="fas fa-sync mr-1"></i> New Cycle</a>
    @endcan
    <a href="{{ route('performance.create') }}" class="btn-primary"><i class="fas fa-plus mr-1"></i> New Review</a>
</x-page-header>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    @foreach($stats as $label => $val)
    <div class="card p-4 flex items-center gap-3">
        <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center"><i class="fas fa-chart-bar text-blue-600"></i></div>
        <div><p class="text-xs text-slate-500">{{ $label }}</p><p class="text-xl font-bold text-slate-800">{{ $val }}</p></div>
    </div>
    @endforeach
</div>

<x-filter-bar :action="route('performance.index')">
    <div class="w-52"><select name="cycle_id" class="form-input w-full select2"><option value="">All Cycles</option>@foreach($cycles as $c)<option value="{{ $c->id }}" @selected(request('cycle_id')==$c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="w-40"><select name="type" class="form-input w-full"><option value="">All Types</option>@foreach(['self','manager','peer'] as $t)<option value="{{ $t }}" @selected(request('type')===$t)>{{ ucfirst($t) }}</option>@endforeach</select></div>
</x-filter-bar>

<x-data-table>
    <thead><tr class="table-header"><th>Employee</th><th>Reviewer</th><th>Cycle</th><th>Type</th><th>Score</th><th>Date</th><th></th></tr></thead>
    <tbody>
    @forelse($reviews as $rv)
    <tr class="table-row">
        <td class="px-4 py-3">
            <div class="flex items-center gap-2">
                <img src="{{ $rv->employee->avatar_url }}" class="w-7 h-7 rounded-full object-cover">
                <span class="font-medium text-slate-800 text-sm">{{ $rv->employee->full_name }}</span>
            </div>
        </td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $rv->reviewer->full_name ?? '—' }}</td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $rv->cycle->name ?? '—' }}</td>
        <td class="px-4 py-3"><span class="badge badge-blue">{{ ucfirst($rv->type) }}</span></td>
        <td class="px-4 py-3">
            <div class="flex items-center gap-2">
                <div class="h-1.5 w-20 bg-slate-100 rounded-full"><div class="h-1.5 rounded-full {{ $rv->total_score >= 80 ? 'bg-green-500' : ($rv->total_score >= 60 ? 'bg-yellow-500' : 'bg-red-400') }}" style="width:{{ min($rv->total_score,100) }}%"></div></div>
                <span class="text-sm font-medium">{{ $rv->total_score }}</span>
            </div>
        </td>
        <td class="px-4 py-3 text-xs text-slate-500">{{ $rv->created_at->format('M d, Y') }}</td>
        <td class="px-4 py-3"><a href="{{ route('performance.show', $rv) }}" class="btn-xs btn-blue">View</a></td>
    </tr>
    @empty
    <tr><td colspan="7" class="text-center py-12 text-slate-400">No reviews found.</td></tr>
    @endforelse
    </tbody>
</x-data-table>
<div class="mt-4">{{ $reviews->withQueryString()->links() }}</div>
@endsection