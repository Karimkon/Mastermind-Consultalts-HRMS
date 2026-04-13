@extends('layouts.app')
@section('title','Audit Logs')
@section('content')
<x-page-header title="Audit Logs" subtitle="System activity trail">
</x-page-header>

<x-filter-bar :action="route('admin.audit.index')">
    <div><label class="form-label">Model Type</label>
        <select name="model_type" class="form-select w-40">
            <option value="">All Types</option>
            @foreach($modelTypes as $type)<option value="{{ $type }}" {{ request('model_type') === $type ? 'selected' : '' }}>{{ $type }}</option>@endforeach
        </select>
    </div>
    <div><label class="form-label">Action</label>
        <select name="action" class="form-select w-32">
            <option value="">All</option>
            @foreach(['created','updated','deleted'] as $a)<option value="{{ $a }}" {{ request('action') === $a ? 'selected' : '' }}>{{ ucfirst($a) }}</option>@endforeach
        </select>
    </div>
    <div><label class="form-label">From</label><input type="date" name="date_from" value="{{ request('date_from') }}" class="form-input w-36"></div>
    <div><label class="form-label">To</label><input type="date" name="date_to" value="{{ request('date_to') }}" class="form-input w-36"></div>
</x-filter-bar>

<x-data-table>
    <thead class="bg-slate-50"><tr>
        <th class="table-head px-4 py-3 text-left">Time</th>
        <th class="table-head px-4 py-3 text-left">User</th>
        <th class="table-head px-4 py-3 text-left">Action</th>
        <th class="table-head px-4 py-3 text-left">Model</th>
        <th class="table-head px-4 py-3 text-left">ID</th>
        <th class="table-head px-4 py-3 text-left">Changes</th>
        <th class="table-head px-4 py-3 text-left">IP</th>
    </tr></thead>
    <tbody class="divide-y divide-slate-100">
    @forelse($logs as $log)
    <tr class="table-row" x-data="{ open: false }">
        <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap">{{ $log->created_at->format('M d H:i') }}</td>
        <td class="px-4 py-3 text-sm text-slate-700">{{ $log->user?->name ?? 'System' }}</td>
        <td class="px-4 py-3">
            <span class="badge-{{ $log->action === 'created' ? 'green' : ($log->action === 'deleted' ? 'red' : 'blue') }}">{{ $log->action }}</span>
        </td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $log->model_type }}</td>
        <td class="px-4 py-3 text-sm text-slate-500">{{ $log->model_id }}</td>
        <td class="px-4 py-3">
            @if($log->new_values)
            <button @click="open=!open" class="text-xs text-blue-600 hover:underline">View changes</button>
            <div x-show="open" x-cloak class="mt-1 bg-slate-50 rounded p-2 text-xs font-mono max-w-xs overflow-auto max-h-24">{{ json_encode($log->new_values, JSON_PRETTY_PRINT) }}</div>
            @else<span class="text-xs text-slate-300">—</span>@endif
        </td>
        <td class="px-4 py-3 text-xs text-slate-400 font-mono">{{ $log->ip_address }}</td>
    </tr>
    @empty
    <tr><td colspan="7" class="py-12 text-center text-slate-400">No audit logs yet.</td></tr>
    @endforelse
    </tbody>
</x-data-table>
<div class="mt-4">{{ $logs->withQueryString()->links() }}</div>
@endsection
