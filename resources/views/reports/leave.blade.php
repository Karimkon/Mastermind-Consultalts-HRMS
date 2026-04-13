@extends('layouts.app')
@section('title', 'Leave Report')
@section('content')
<x-page-header title="Leave Report" subtitle="Leave summary and balances">
    <a href="{{ route('reports.export', ['type'=>'leave', 'year'=>request('year')]) }}" class="btn-secondary"><i class="fas fa-file-excel mr-1 text-green-600"></i> Export</a>
</x-page-header>

<x-filter-bar :action="route('reports.leave')">
    <div class="w-24"><input type="number" name="year" class="form-input" value="{{ request('year', date('Y')) }}" min="2020"></div>
    <div class="w-48"><select name="leave_type_id" class="form-input w-full"><option value="">All Leave Types</option>@foreach($leaveTypes as $lt)<option value="{{ $lt->id }}" @selected(request('leave_type_id')==$lt->id)>{{ $lt->name }}</option>@endforeach</select></div>
    <div class="w-36"><select name="status" class="form-input w-full"><option value="">All Status</option>@foreach(['pending','approved','rejected'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
</x-filter-bar>

<x-data-table>
    <thead><tr class="table-header"><th>Employee</th><th>Leave Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th><th>Approved By</th></tr></thead>
    <tbody>
    @forelse($leaves as $leave)
    <tr class="table-row">
        <td class="px-4 py-3">
            <div class="flex items-center gap-2">
                <img src="{{ $leave->employee->avatar_url }}" class="w-7 h-7 rounded-full">
                <span class="text-sm font-medium text-slate-800">{{ $leave->employee->full_name }}</span>
            </div>
        </td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $leave->leaveType->name ?? '—' }}</td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $leave->from_date }}</td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $leave->to_date }}</td>
        <td class="px-4 py-3 text-sm font-medium text-slate-700">{{ $leave->days_count }}</td>
        <td class="px-4 py-3"><span class="badge {{ $leave->status === 'approved' ? 'badge-green' : ($leave->status === 'rejected' ? 'badge-red' : 'badge-yellow') }}">{{ ucfirst($leave->status) }}</span></td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $leave->approver->full_name ?? '—' }}</td>
    </tr>
    @empty
    <tr><td colspan="7" class="text-center py-8 text-slate-400">No leave records found.</td></tr>
    @endforelse
    </tbody>
</x-data-table>
<div class="mt-4">{{ $leaves->withQueryString()->links() }}</div>
@endsection