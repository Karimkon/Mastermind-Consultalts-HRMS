@extends("layouts.app")
@section("title","Attendance")
@section("content")
<x-page-header title="Attendance" subtitle="Track employee attendance records">
    @can("attendance.manage")<a href="{{ route('attendance.create') }}" class="btn-primary"><i class="fas fa-plus"></i> Mark Attendance</a>@endcan
    <a href="{{ route('attendance.report') }}" class="btn-secondary"><i class="fas fa-file-alt"></i> Monthly Report</a>
    <a href="{{ route('attendance.holidays') }}" class="btn-secondary"><i class="fas fa-umbrella-beach"></i> Holidays</a>
</x-page-header>

{{-- Summary Cards --}}
<div class="grid grid-cols-4 gap-4 mb-6">
    <x-stat-card icon="fas fa-check-circle" label="Present" :value="$summary['present']" color="green" />
    <x-stat-card icon="fas fa-times-circle" label="Absent" :value="$summary['absent']" color="red" />
    <x-stat-card icon="fas fa-clock" label="Late" :value="$summary['late']" color="yellow" />
    <x-stat-card icon="fas fa-users" label="Total Active" :value="$summary['total']" color="blue" />
</div>

{{-- Clock In/Out (for employees) --}}
@if(auth()->user()->hasRole("employee"))
<div class="card p-6 mb-6" x-data="clockSystem()">
    <h3 class="font-semibold text-slate-800 mb-4">My Attendance — Today</h3>
    <div class="flex items-center gap-4">
        <div class="text-4xl font-bold text-slate-900 tabular-nums" x-text="time"></div>
        <div class="flex gap-3">
            <button @click="clockIn()" class="btn-primary px-6"><i class="fas fa-sign-in-alt"></i> Clock In</button>
            <button @click="clockOut()" class="btn-secondary px-6"><i class="fas fa-sign-out-alt"></i> Clock Out</button>
        </div>
        <p x-text="message" class="text-sm text-slate-600"></p>
    </div>
</div>
@endif

<x-filter-bar :action="route('attendance.index')">
    <div><label class="form-label">Date</label><input type="date" name="date" value="{{ $date }}" class="form-input"></div>
    <div><label class="form-label">Department</label>
        <select name="department_id" class="form-select w-44 select2">
            <option value="">All Departments</option>
            @foreach($departments as $dept)<option value="{{ $dept->id }}" {{ request('department_id')==$dept->id?'selected':'' }}>{{ $dept->name }}</option>@endforeach
        </select>
    </div>
    <div><label class="form-label">Status</label>
        <select name="status" class="form-select w-36">
            <option value="">All</option>
            @foreach(["present"=>"Present","absent"=>"Absent","late"=>"Late","half_day"=>"Half Day"] as $v=>$l)<option value="{{ $v }}" {{ request('status')==$v?'selected':'' }}>{{ $l }}</option>@endforeach
        </select>
    </div>
</x-filter-bar>

<x-data-table>
    <thead class="bg-slate-50"><tr>
        <th class="table-head px-6 py-3 text-left">Employee</th>
        <th class="table-head px-4 py-3 text-left">Clock In</th>
        <th class="table-head px-4 py-3 text-left">Clock Out</th>
        <th class="table-head px-4 py-3 text-left">Hours</th>
        <th class="table-head px-4 py-3 text-left">Overtime</th>
        <th class="table-head px-4 py-3 text-left">Status</th>
    </tr></thead>
    <tbody class="divide-y divide-slate-100">
        @forelse($logs as $log)
        <tr class="table-row">
            <td class="px-6 py-3"><div class="flex items-center gap-3"><img src="{{ $log->employee->avatar_url }}" class="w-8 h-8 rounded-full object-cover"><div><p class="text-sm font-medium text-slate-800">{{ $log->employee->full_name }}</p><p class="text-xs text-slate-500">{{ $log->employee->department?->name }}</p></div></div></td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $log->clock_in?->format('H:i') ?? '—' }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $log->clock_out?->format('H:i') ?? '—' }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">
                @if($log->clock_in && $log->clock_out){{ number_format($log->clock_in->diffInHours($log->clock_out), 1) }}h@else—@endif
            </td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $log->overtime_hours > 0 ? $log->overtime_hours . 'h' : '—' }}</td>
            <td class="px-4 py-3"><span class="badge-{{ $log->status==='present'?'green':($log->status==='late'?'yellow':'red') }}">{{ ucfirst($log->status) }}</span></td>
        </tr>
        @empty
        <tr><td colspan="6" class="py-12 text-center text-slate-400">No attendance records for {{ $date }}</td></tr>
        @endforelse
    </tbody>
</x-data-table>
<div class="mt-4">{{ $logs->withQueryString()->links() }}</div>
@endsection
@push("scripts")
<script>
function clockSystem() { return {
    time: new Date().toLocaleTimeString(),
    message: "",
    init() { setInterval(() => this.time = new Date().toLocaleTimeString(), 1000); },
    clockIn() {
        $.post("{{ route('attendance.clock-in') }}", {}, d => this.message = d.message)
         .fail(e => this.message = e.responseJSON?.message ?? "Error");
    },
    clockOut() {
        $.post("{{ route('attendance.clock-out') }}", {}, d => this.message = d.message)
         .fail(e => this.message = e.responseJSON?.message ?? "Error");
    }
}; }
</script>
@endpush
