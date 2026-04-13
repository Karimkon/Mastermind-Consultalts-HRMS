@extends('layouts.app')
@section('title', 'Performance Cycles')
@section('content')
<x-page-header title="Performance Cycles" subtitle="Evaluation periods">
    <a href="{{ route('performance.cycles.create') }}" class="btn-primary"><i class="fas fa-plus mr-1"></i> New Cycle</a>
</x-page-header>

<x-data-table>
    <thead><tr class="table-header"><th>Cycle Name</th><th>Year</th><th>Start</th><th>End</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse($cycles as $cycle)
    <tr class="table-row">
        <td class="px-4 py-3 font-medium text-slate-800">{{ $cycle->name }}</td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $cycle->year }}</td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $cycle->start_date }}</td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $cycle->end_date }}</td>
        <td class="px-4 py-3"><span class="badge {{ $cycle->status === 'active' ? 'badge-green' : 'badge-slate' }}">{{ ucfirst($cycle->status) }}</span></td>
        <td class="px-4 py-3"><a href="{{ route('performance.cycles.edit', $cycle) }}" class="btn-xs btn-amber">Edit</a></td>
    </tr>
    @empty
    <tr><td colspan="6" class="text-center py-8 text-slate-400">No cycles created.</td></tr>
    @endforelse
    </tbody>
</x-data-table>
@endsection