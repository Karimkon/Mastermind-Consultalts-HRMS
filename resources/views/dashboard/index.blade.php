@extends("layouts.app")
@section("title", "Dashboard")
@section("breadcrumb") <span class="text-slate-600 text-sm font-medium">Dashboard</span> @endsection

@section("content")
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Good {{ now()->hour < 12 ? 'Morning' : (now()->hour < 17 ? 'Afternoon' : 'Evening') }}, {{ auth()->user()->name }} 👋</h1>
    <p class="text-slate-500 text-sm mt-1">{{ now()->format('l, F j, Y') }}</p>
</div>

{{-- Stat Cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-stat-card icon="fas fa-users" label="Total Employees" :value="$stats['total_employees']" color="blue" />
    <x-stat-card icon="fas fa-user-check" label="Present Today" :value="$stats['present_today']" color="green" />
    <x-stat-card icon="fas fa-calendar-minus" label="On Leave Today" :value="$stats['on_leave_today']" color="yellow" />
    <x-stat-card icon="fas fa-clock" label="Pending Leaves" :value="$stats['pending_leaves']" color="red" />
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-stat-card icon="fas fa-briefcase" label="Open Jobs" :value="$stats['open_jobs']" color="purple" />
    <x-stat-card icon="fas fa-video" label="Meetings Today" :value="$stats['meetings_today']" color="indigo" />
    <x-stat-card icon="fas fa-graduation-cap" label="Active Trainings" :value="$stats['trainings_active']" color="teal" />
    <a href="{{ route('attendance.index') }}" class="card p-4 hover:shadow-md transition-shadow flex items-center gap-4 group">
        <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center group-hover:bg-blue-100 transition-colors">
            <i class="fas fa-arrow-right text-slate-400 group-hover:text-blue-600 text-sm"></i>
        </div>
        <div><p class="text-xs text-slate-500">Quick Link</p><p class="text-sm font-semibold text-slate-700">View Attendance</p></div>
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    {{-- Attendance Chart --}}
    <div class="lg:col-span-2 card p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-slate-800">7-Day Attendance Trend</h3>
            <span class="text-xs text-slate-400">Last 7 days</span>
        </div>
        <div id="attendanceChart" style="height:220px"></div>
    </div>

    {{-- Department Headcount --}}
    <div class="card p-6">
        <h3 class="font-semibold text-slate-800 mb-4">Headcount by Department</h3>
        <div id="headcountChart" style="height:220px"></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Pending Leave Requests --}}
    <div class="card">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
            <h3 class="font-semibold text-slate-800">Pending Leave Requests</h3>
            <a href="{{ route('leaves.index') }}" class="text-xs text-blue-600 hover:underline">View all</a>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($pendingLeaves as $leave)
            <div class="flex items-center gap-4 px-6 py-3">
                <img src="{{ $leave->employee->avatar_url }}" class="w-9 h-9 rounded-full object-cover border-2 border-slate-200">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-800 truncate">{{ $leave->employee->full_name }}</p>
                    <p class="text-xs text-slate-500">{{ $leave->leaveType->name }} · {{ $leave->days_count }} day(s)</p>
                </div>
                <span class="badge-yellow text-xs">Pending</span>
            </div>
            @empty
            <div class="px-6 py-8 text-center text-sm text-slate-400"><i class="fas fa-check-circle text-green-400 text-2xl mb-2 block"></i>All caught up!</div>
            @endforelse
        </div>
    </div>

    {{-- Recent Employees --}}
    <div class="card">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
            <h3 class="font-semibold text-slate-800">Recent Employees</h3>
            <a href="{{ route('employees.create') }}" class="text-xs text-blue-600 hover:underline">+ Add new</a>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($recentEmployees as $employee)
            <div class="flex items-center gap-4 px-6 py-3">
                <img src="{{ $employee->avatar_url }}" class="w-9 h-9 rounded-full object-cover border-2 border-slate-200">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-800 truncate">{{ $employee->full_name }}</p>
                    <p class="text-xs text-slate-500">{{ $employee->department?->name }} · {{ $employee->designation?->title }}</p>
                </div>
                <a href="{{ route('employees.show', $employee) }}" class="text-xs text-blue-600 hover:underline">View</a>
            </div>
            @empty
            <div class="px-6 py-8 text-center text-sm text-slate-400">No employees yet</div>
            @endforelse
        </div>
    </div>
</div>

{{-- Upcoming Meetings & Today Attendance --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
    <div class="card">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
            <h3 class="font-semibold text-slate-800">Upcoming Meetings</h3>
            <a href="{{ route('meetings.create') }}" class="text-xs text-blue-600 hover:underline">+ Schedule</a>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($upcomingMeetings as $meeting)
            <div class="px-6 py-3">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-800">{{ $meeting->title }}</p>
                        <p class="text-xs text-slate-500 mt-0.5"><i class="fas fa-clock mr-1"></i>{{ $meeting->start_at->format('M d, H:i') }} · {{ $meeting->location ?? 'Online' }}</p>
                    </div>
                    <span class="badge-blue text-xs">{{ ucfirst($meeting->type) }}</span>
                </div>
            </div>
            @empty
            <div class="px-6 py-8 text-center text-sm text-slate-400">No upcoming meetings</div>
            @endforelse
        </div>
    </div>

    <div class="card">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
            <h3 class="font-semibold text-slate-800">Today's Clock-ins</h3>
            <a href="{{ route('attendance.index') }}" class="text-xs text-blue-600 hover:underline">View all</a>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($todayAttendance as $log)
            <div class="flex items-center gap-3 px-6 py-2.5">
                <img src="{{ $log->employee->avatar_url }}" class="w-8 h-8 rounded-full object-cover border border-slate-200">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-800 truncate">{{ $log->employee->full_name }}</p>
                    <p class="text-xs text-slate-500">In: {{ $log->clock_in?->format('H:i') }} {{ $log->clock_out ? "· Out: " . $log->clock_out->format('H:i') : '' }}</p>
                </div>
                <span class="badge-{{ $log->status === 'present' ? 'green' : ($log->status === 'late' ? 'yellow' : 'red') }} text-xs">{{ ucfirst($log->status) }}</span>
            </div>
            @empty
            <div class="px-6 py-8 text-center text-sm text-slate-400">No attendance records for today</div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push("scripts")
<script>
// 7-Day Attendance Chart
fetch("/ajax/charts/attendance").then(r=>r.json()).then(data=>{
    new ApexCharts(document.getElementById("attendanceChart"), {
        chart: { type: "area", height: 220, toolbar: { show: false }, sparkline: { enabled: false } },
        series: [
            { name: "Present", data: data.map(d=>d.present) },
            { name: "Absent",  data: data.map(d=>d.absent) },
            { name: "Late",    data: data.map(d=>d.late) },
        ],
        xaxis: { categories: data.map(d=>d.date), labels: { style: { fontSize: "11px" } } },
        colors: ["#22c55e","#ef4444","#f59e0b"],
        fill: { type: "gradient", gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
        stroke: { curve: "smooth", width: 2 },
        legend: { position: "top", fontSize: "12px" },
        grid: { borderColor: "#f1f5f9", strokeDashArray: 4 },
        dataLabels: { enabled: false },
    }).render();
});

// Headcount Donut Chart
fetch("/ajax/charts/headcount").then(r=>r.json()).then(data=>{
    new ApexCharts(document.getElementById("headcountChart"), {
        chart: { type: "donut", height: 220 },
        series: data.map(d=>d.count),
        labels: data.map(d=>d.label),
        colors: ["#3b82f6","#22c55e","#f59e0b","#ef4444","#8b5cf6","#ec4899"],
        legend: { position: "bottom", fontSize: "11px" },
        plotOptions: { pie: { donut: { size: "60%" } } },
        dataLabels: { enabled: false },
    }).render();
});
</script>
@endpush
