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
    {{-- present + absent + late for the current filter, not a headcount --}}
    <x-stat-card icon="fas fa-list-check" label="Records Shown" :value="$summary['total']" color="blue" />
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
    <div><label class="form-label">Date</label>
        <input type="date" name="date" value="{{ $date }}" class="form-input">
    </div>
    <div><label class="form-label">From</label>
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-input">
    </div>
    <div><label class="form-label">To</label>
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-input">
    </div>
    {{-- Client, section and department are only meaningful to someone who can
         see more than their own record. --}}
    @if($canSeeAll)
    <div><label class="form-label">Client</label>
        <select name="client_id" class="form-select w-48 select2">
            <option value="">All Clients</option>
            @foreach($clients as $client)
                <option value="{{ $client->id }}" {{ request('client_id')==$client->id?'selected':'' }}>{{ $client->company_name }}</option>
            @endforeach
        </select>
    </div>
    <div><label class="form-label">Section</label>
        <select name="section" class="form-select w-44 select2">
            <option value="">All Sections</option>
            @foreach($sections as $section)
                <option value="{{ $section }}" {{ request('section')===$section?'selected':'' }}>{{ $section }}</option>
            @endforeach
        </select>
    </div>
    <div><label class="form-label">Department</label>
        <select name="department_id" class="form-select w-44 select2">
            <option value="">All Departments</option>
            @foreach($departments as $dept)<option value="{{ $dept->id }}" {{ request('department_id')==$dept->id?'selected':'' }}>{{ $dept->name }}</option>@endforeach
        </select>
    </div>
    @endif
    <div><label class="form-label">Status</label>
        <select name="status" class="form-select w-36">
            <option value="">All</option>
            @foreach(["present"=>"Present","absent"=>"Absent","late"=>"Late","half_day"=>"Half Day","leave"=>"On Leave"] as $v=>$l)<option value="{{ $v }}" {{ request('status')==$v?'selected':'' }}>{{ $l }}</option>@endforeach
        </select>
    </div>
</x-filter-bar>

<x-data-table>
    <thead class="bg-slate-50"><tr>
        <th class="table-head px-6 py-3 text-left">Employee</th>
        <th class="table-head px-4 py-3 text-left">Date</th>
        @if($canSeeAll)
        <th class="table-head px-4 py-3 text-left">Client / Section</th>
        @endif
        <th class="table-head px-4 py-3 text-left">Clock In</th>
        {{-- Whether the clock-in could be checked at all. Without this the
             register cannot distinguish somebody standing on site from
             somebody clocking in from home on an unmapped client. --}}
        <th class="table-head px-4 py-3 text-left">Location</th>
        <th class="table-head px-4 py-3 text-left">Clock Out</th>
        <th class="table-head px-4 py-3 text-left">Hours</th>
        <th class="table-head px-4 py-3 text-left">Overtime</th>
        <th class="table-head px-4 py-3 text-left">Status</th>
    </tr></thead>
    <tbody class="divide-y divide-slate-100">
        @forelse($logs as $log)
        <tr class="table-row">
            <td class="px-6 py-3"><div class="flex items-center gap-3"><img src="{{ $log->employee->avatar_url }}" class="w-8 h-8 rounded-full object-cover"><div><p class="text-sm font-medium text-slate-800">{{ $log->employee->full_name }}</p><p class="text-xs text-slate-500">{{ $log->employee->department?->name }}</p></div></div></td>
            <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap">{{ $log->date?->format('d M Y') ?? '—' }}</td>
            @if($canSeeAll)
            <td class="px-4 py-3 text-sm">
                <p class="text-slate-700">{{ $log->client?->company_name ?? $log->employee->clients->first()?->company_name ?? '—' }}</p>
                <p class="text-xs text-slate-400">{{ $log->employee->work_location ?: '—' }}</p>
            </td>
            @endif
            <td class="px-4 py-3 text-sm text-slate-600">{{ $log->clock_in?->format('H:i') ?? '—' }}</td>
            <td class="px-4 py-3 text-sm">
                @php($loc = $log->location_status)
                @if($loc === \App\Models\AttendanceLog::LOCATION_VERIFIED)
                    <span class="inline-flex items-center gap-1 text-emerald-700">
                        <i class="fas fa-location-dot text-xs"></i>
                        {{-- No inline @if here. Blade does not treat a directive as
                             one when it follows a word character -- "site@if" reads
                             as text, like an email address -- but the trailing
                             @endif still parses, so it closed the outer @if and
                             orphaned the @elseif below. --}}
                        On site{{ $log->distance_metres !== null ? ' · '.round($log->distance_metres).'m' : '' }}
                    </span>
                @elseif($loc === \App\Models\AttendanceLog::LOCATION_OUTSIDE)
                    <span class="inline-flex items-center gap-1 text-red-700 font-medium">
                        <i class="fas fa-triangle-exclamation text-xs"></i>
                        {{ round($log->distance_metres) }}m away
                    </span>
                @elseif($loc)
                    {{-- Not a failure by the employee. The check could not be made,
                         and saying so is the whole point of the column. --}}
                    <span class="inline-flex items-center gap-1 text-slate-400" title="{{ $log->locationLabel() }}">
                        <i class="fas fa-circle-question text-xs"></i>
                        Not verified
                    </span>
                @else
                    <span class="text-slate-300">—</span>
                @endif
            </td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $log->clock_out?->format('H:i') ?? '—' }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">
                @if($log->clock_in && $log->clock_out){{ number_format($log->clock_in->diffInHours($log->clock_out), 1) }}h@else—@endif
            </td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $log->overtime_hours > 0 ? $log->overtime_hours . 'h' : '—' }}</td>
            <td class="px-4 py-3"><span class="badge-{{ $log->status==='present'?'green':($log->status==='late'?'yellow':'red') }}">{{ ucfirst($log->status) }}</span></td>
        </tr>
        @empty
        <tr><td colspan="{{ $canSeeAll ? 8 : 7 }}" class="py-12 text-center">
            <i class="fas fa-calendar-xmark text-3xl text-slate-300 mb-3 block"></i>
            <p class="text-slate-500 text-sm">
                No attendance records{{ $date ? ' for ' . $date : ' match these filters' }}.
            </p>
            @if($canSeeAll)
            <p class="text-slate-400 text-xs mt-1">
                Records appear when employees clock in, or when you add them with <strong>Mark Attendance</strong>.
            </p>
            @endif
        </td></tr>
        @endforelse
    </tbody>
</x-data-table>
<div class="mt-4">{{ $logs->links() }}</div>
@endsection
@push("scripts")
<script>
function clockSystem() { return {
    time: new Date().toLocaleTimeString(),
    message: "",
    success: true,
    loading: false,
    geoEnabled: {{ $geoEnabled ? "true" : "false" }},
    init() { setInterval(() => this.time = new Date().toLocaleTimeString(), 1000); },
    getLocation() {
        return new Promise((resolve, reject) => {
            if (!this.geoEnabled) return resolve({lat: null, lng: null});
            if (!navigator.geolocation) return reject("Geolocation not supported by this browser.");
            navigator.geolocation.getCurrentPosition(
                pos => resolve({lat: pos.coords.latitude, lng: pos.coords.longitude}),
                () => reject("Location access denied. Please allow location to clock in/out."),
                {timeout: 10000}
            );
        });
    },
    async clockIn() {
        this.loading = true; this.message = "";
        try {
            const loc = await this.getLocation();
            const data = await new Promise((ok, fail) => {
                $.post("{{ route('attendance.clock-in') }}", loc, ok).fail(e => fail(e.responseJSON?.error ?? "Error"));
            });
            this.message = data.message; this.success = true;
        } catch(e) { this.message = e; this.success = false; }
        this.loading = false;
    },
    async clockOut() {
        this.loading = true; this.message = "";
        try {
            const loc = await this.getLocation();
            const data = await new Promise((ok, fail) => {
                $.post("{{ route('attendance.clock-out') }}", loc, ok).fail(e => fail(e.responseJSON?.error ?? "Error"));
            });
            this.message = "Clocked out at " + data.time + " (" + data.hours + "h)"; this.success = true;
        } catch(e) { this.message = e; this.success = false; }
        this.loading = false;
    }
}; }
</script>
@endpush
