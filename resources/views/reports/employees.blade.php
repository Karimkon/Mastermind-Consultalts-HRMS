@extends('layouts.app')
@section('title', 'Employee Report')
@section('content')
<x-page-header title="Employee Report" subtitle="Workforce summary">
    <a href="{{ route('export.employees') }}" class="btn-secondary"><i class="fas fa-file-excel mr-1 text-green-600"></i> Export Excel</a>
</x-page-header>

<x-filter-bar :action="route('reports.employees')">
    <div class="w-48"><select name="department_id" class="form-input w-full select2"><option value="">All Departments</option>@foreach($departments as $d)<option value="{{ $d->id }}" @selected(request('department_id')==$d->id)>{{ $d->name }}</option>@endforeach</select></div>
    <div class="w-36"><select name="status" class="form-input w-full"><option value="">All Status</option>@foreach(['active','on-leave','terminated'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
    <div class="w-36"><select name="type" class="form-input w-full"><option value="">All Types</option>@foreach(['full-time','part-time','contract'] as $t)<option value="{{ $t }}" @selected(request('type')===$t)>{{ ucfirst($t) }}</option>@endforeach</select></div>
</x-filter-bar>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
    <div class="card p-4 text-center"><p class="text-3xl font-bold text-blue-600">{{ $stats['total'] }}</p><p class="text-xs text-slate-500 mt-1">Total Employees</p></div>
    <div class="card p-4 text-center"><p class="text-3xl font-bold text-green-600">{{ $stats['active'] }}</p><p class="text-xs text-slate-500 mt-1">Active</p></div>
    <div class="card p-4 text-center"><p class="text-3xl font-bold text-yellow-600">{{ $stats['on_leave'] }}</p><p class="text-xs text-slate-500 mt-1">On Leave</p></div>
    <div class="card p-4 text-center"><p class="text-3xl font-bold text-red-600">{{ $stats['terminated'] }}</p><p class="text-xs text-slate-500 mt-1">Terminated</p></div>
</div>

<x-data-table>
    <thead><tr class="table-header"><th>Employee</th><th>Department</th><th>Designation</th><th>Type</th><th>Status</th><th>Hire Date</th></tr></thead>
    <tbody>
    @foreach($employees as $emp)
    <tr class="table-row">
        <td class="px-4 py-3">
            <div class="flex items-center gap-2">
                <img src="{{ $emp->avatar_url }}" class="w-7 h-7 rounded-full object-cover">
                <div><p class="text-sm font-medium text-slate-800">{{ $emp->full_name }}</p><p class="text-xs text-slate-500">{{ $emp->emp_number }}</p></div>
            </div>
        </td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $emp->department->name ?? '—' }}</td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $emp->designation->title ?? '—' }}</td>
        <td class="px-4 py-3 text-sm">{{ ucfirst($emp->employment_type ?? '—') }}</td>
        <td class="px-4 py-3">{!! $emp->status_badge !!}</td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $emp->hire_date }}</td>
    </tr>
    @endforeach
    </tbody>
</x-data-table>
<div class="mt-4">{{ $employees->withQueryString()->links() }}</div>
@endsection