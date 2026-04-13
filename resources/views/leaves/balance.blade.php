@extends("layouts.app")
@section("title","Leave Balance")
@section("content")
<x-page-header title="Leave Balances — {{ request('year', date('Y')) }}">
    <a href="{{ route('leaves.index') }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</x-page-header>

{{-- Filter --}}
<x-filter-bar :action="route('leaves.balance')">
    <div>
        <label class="form-label">Employee</label>
        <select name="employee_id" class="form-select w-48 select2">
            <option value="">All Employees</option>
            @foreach($employees as $emp)
            <option value="{{ $emp->id }}" {{ request('employee_id') == $emp->id ? 'selected' : '' }}>{{ $emp->full_name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="form-label">Year</label>
        <input type="number" name="year" value="{{ request('year', date('Y')) }}" class="form-input w-24">
    </div>
</x-filter-bar>

<x-data-table>
    <thead><tr class="table-header"><th>Employee</th><th>Leave Type</th><th>Total Days</th><th>Used</th><th>Pending</th><th>Remaining</th></tr></thead>
    <tbody>
    @forelse($balances as $bal)
    @php $remaining = $bal->total_days - $bal->used_days - $bal->pending_days; @endphp
    <tr class="table-row">
        <td class="px-4 py-3"><div class="flex items-center gap-2"><img src="{{ $bal->employee->avatar_url }}" class="w-7 h-7 rounded-full"><span class="text-sm font-medium text-slate-800">{{ $bal->employee->full_name }}</span></div></td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $bal->leaveType->name ?? '—' }}</td>
        <td class="px-4 py-3 text-sm text-slate-700">{{ $bal->total_days }}</td>
        <td class="px-4 py-3 text-sm text-orange-600">{{ $bal->used_days }}</td>
        <td class="px-4 py-3 text-sm text-yellow-600">{{ $bal->pending_days }}</td>
        <td class="px-4 py-3 text-sm font-bold {{ $remaining > 5 ? 'text-green-600' : 'text-red-500' }}">{{ max(0, $remaining) }}</td>
    </tr>
    @empty
    <tr><td colspan="6" class="text-center py-8 text-slate-400">No balance records found.</td></tr>
    @endforelse
    </tbody>
</x-data-table>
<div class="mt-4">{{ $balances->withQueryString()->links() }}</div>
@endsection
