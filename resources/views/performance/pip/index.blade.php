@extends('layouts.app')
@section('title','Performance Improvement Plans')
@section('content')
<x-page-header title="Performance Improvement Plans" subtitle="Manage active PIPs">
    <a href="{{ route('pips.create') }}" class="btn-primary"><i class="fas fa-plus"></i> New PIP</a>
</x-page-header>

<x-data-table>
    <thead class="bg-slate-50"><tr>
        <th class="table-head px-6 py-3 text-left">Employee</th>
        <th class="table-head px-4 py-3 text-left">Title</th>
        <th class="table-head px-4 py-3 text-left">Period</th>
        <th class="table-head px-4 py-3 text-left">Status</th>
        <th class="table-head px-4 py-3 text-left">Actions</th>
    </tr></thead>
    <tbody class="divide-y divide-slate-100">
    @forelse($pips as $pip)
    <tr class="table-row">
        <td class="px-6 py-3"><div class="flex items-center gap-3"><img src="{{ $pip->employee->avatar_url }}" class="w-8 h-8 rounded-full"><p class="text-sm font-medium text-slate-800">{{ $pip->employee->full_name }}</p></div></td>
        <td class="px-4 py-3 text-sm text-slate-700">{{ $pip->title }}</td>
        <td class="px-4 py-3 text-xs text-slate-500">{{ $pip->start_date->format('M d') }} – {{ $pip->end_date->format('M d, Y') }}</td>
        <td class="px-4 py-3"><span class="badge-{{ $pip->status === 'active' ? 'yellow' : ($pip->status === 'completed' ? 'green' : 'red') }}">{{ ucfirst($pip->status) }}</span></td>
        <td class="px-4 py-3">
            <a href="{{ route('pips.show', $pip) }}" class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg"><i class="fas fa-eye text-xs"></i></a>
        </td>
    </tr>
    @empty
    <tr><td colspan="5" class="py-12 text-center text-slate-400">No PIPs found.</td></tr>
    @endforelse
    </tbody>
</x-data-table>
<div class="mt-4">{{ $pips->links() }}</div>
@endsection
