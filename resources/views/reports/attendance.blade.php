@extends('layouts.app')
@section('title', 'Attendance Report')
@section('content')
<x-page-header title="Attendance Report" subtitle="Summary by period">
    <a href="{{ route('export.attendance', ['month'=>request('month', date('n')), 'year'=>request('year', date('Y'))]) }}" class="btn-secondary"><i class="fas fa-file-excel mr-1 text-green-600"></i> Export Excel</a>
</x-page-header>

<x-filter-bar :action="route('reports.attendance')">
    <div class="w-36"><select name="month" class="form-input w-full">@for($m=1;$m<=12;$m++)<option value="{{ $m }}" @selected(request('month',date('n'))==$m)>{{ date('F', mktime(0,0,0,$m,1)) }}</option>@endfor</select></div>
    <div class="w-24"><input type="number" name="year" class="form-input" value="{{ request('year', date('Y')) }}" min="2020" max="{{ date('Y') }}"></div>
    <div class="w-52"><select name="department_id" class="form-input w-full select2"><option value="">All Departments</option>@foreach($departments as $d)<option value="{{ $d->id }}" @selected(request('department_id')==$d->id)>{{ $d->name }}</option>@endforeach</select></div>
</x-filter-bar>

<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-4">
    @foreach(['present'=>'green','absent'=>'red','late'=>'yellow','half-day'=>'orange','overtime'=>'blue'] as $status => $color)
    <div class="card p-4 text-center">
        <p class="text-2xl font-bold text-{{ $color }}-600">{{ $stats[$status] ?? 0 }}</p>
        <p class="text-xs text-slate-500 mt-1">{{ ucfirst($status) }}</p>
    </div>
    @endforeach
</div>

<x-data-table>
    <thead><tr class="table-header"><th>Employee</th><th>Department</th><th>Present</th><th>Absent</th><th>Late</th><th>Overtime Hrs</th><th>Attendance %</th></tr></thead>
    <tbody>
    @forelse($summary as $row)
    <tr class="table-row">
        <td class="px-4 py-3">
            <div class="flex items-center gap-2">
                <img src="{{ $row['employee']->avatar_url }}" class="w-7 h-7 rounded-full">
                <span class="text-sm font-medium text-slate-800">{{ $row['employee']->full_name }}</span>
            </div>
        </td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ $row['employee']->department->name ?? '—' }}</td>
        <td class="px-4 py-3 text-sm text-green-600 font-medium">{{ $row['present'] }}</td>
        <td class="px-4 py-3 text-sm text-red-500 font-medium">{{ $row['absent'] }}</td>
        <td class="px-4 py-3 text-sm text-yellow-600 font-medium">{{ $row['late'] }}</td>
        <td class="px-4 py-3 text-sm text-slate-600">{{ number_format($row['overtime_hours'],1) }}</td>
        <td class="px-4 py-3">
            <div class="flex items-center gap-2">
                <div class="h-1.5 w-16 bg-slate-100 rounded-full"><div class="h-1.5 rounded-full {{ $row['pct'] >= 90 ? 'bg-green-500' : ($row['pct'] >= 75 ? 'bg-yellow-500' : 'bg-red-400') }}" style="width:{{ $row['pct'] }}%"></div></div>
                <span class="text-xs font-medium">{{ $row['pct'] }}%</span>
            </div>
        </td>
    </tr>
    @empty
    <tr><td colspan="7" class="text-center py-8 text-slate-400">No attendance data found.</td></tr>
    @endforelse
    </tbody>
</x-data-table>
@endsection