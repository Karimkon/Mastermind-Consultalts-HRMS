@extends("layouts.app")
@section("title","Attendance Report")
@section("content")
<x-page-header title="Monthly Attendance Report">
    <form method="GET" action="{{ route('attendance.report') }}" class="flex gap-2 items-center">
        <input type="month" name="month" value="{{ $month }}" class="form-input w-44">
        <button type="submit" class="btn-primary"><i class="fas fa-filter"></i> Filter</button>
    </form>
    <a href="{{ route('reports.export', 'attendance') }}?month={{ $month }}" class="btn-secondary"><i class="fas fa-download"></i> Export CSV</a>
</x-page-header>
<x-data-table>
    <thead class="bg-slate-50"><tr>
        <th class="table-head px-6 py-3 text-left">Employee</th>
        <th class="table-head px-4 py-3 text-center">Present</th>
        <th class="table-head px-4 py-3 text-center">Absent</th>
        <th class="table-head px-4 py-3 text-center">Late</th>
        <th class="table-head px-4 py-3 text-center">Total Hours</th>
        <th class="table-head px-4 py-3 text-center">Overtime</th>
    </tr></thead>
    <tbody class="divide-y divide-slate-100">
        @foreach($employees as $emp)
        @php
            $logs     = $emp->attendanceLogs;
            $present  = $logs->where("status","present")->count();
            $absent   = $logs->where("status","absent")->count();
            $late     = $logs->where("status","late")->count();
            $overtime = $logs->sum("overtime_hours");
        @endphp
        <tr class="table-row">
            <td class="px-6 py-3"><div class="flex items-center gap-3"><img src="{{ $emp->avatar_url }}" class="w-8 h-8 rounded-full"><div><p class="text-sm font-medium text-slate-800">{{ $emp->full_name }}</p><p class="text-xs text-slate-500">{{ $emp->department?->name }}</p></div></div></td>
            <td class="px-4 py-3 text-center text-sm font-semibold text-green-600">{{ $present }}</td>
            <td class="px-4 py-3 text-center text-sm font-semibold text-red-500">{{ $absent }}</td>
            <td class="px-4 py-3 text-center text-sm font-semibold text-yellow-600">{{ $late }}</td>
            <td class="px-4 py-3 text-center text-sm text-slate-600">—</td>
            <td class="px-4 py-3 text-center text-sm text-slate-600">{{ number_format($overtime,1) }}h</td>
        </tr>
        @endforeach
    </tbody>
</x-data-table>
@endsection
