@extends('layouts.app')
@section('title','Office Attendance')
@section('content')

<x-page-header title="Office Attendance"
    subtitle="Daily clock in and out at the Mastermind head office — record only, does not affect pay">
    <a href="{{ route('am-visits.index') }}" class="btn-secondary">
        <i class="fas fa-location-dot"></i> Client Site Visits
    </a>
</x-page-header>

@foreach(['success' => ['green','check-circle'], 'warning' => ['amber','triangle-exclamation'], 'error' => ['red','circle-exclamation']] as $key => [$c,$icon])
    @if(session($key))
    <div class="mb-4 flex items-center gap-3 px-4 py-3 bg-{{ $c }}-50 border border-{{ $c }}-200 rounded-lg text-{{ $c }}-700 text-sm">
        <i class="fas fa-{{ $icon }}"></i> {{ session($key) }}
    </div>
    @endif
@endforeach

@unless($office)
<div class="mb-5 flex items-start gap-3 p-4 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-sm">
    <i class="fas fa-map-location-dot mt-0.5"></i>
    <div>
        <p class="font-semibold">The office location has not been set yet.</p>
        <p>Clock in and out still work and are recorded — they simply cannot be checked against the office
        until the coordinates are set under
        @role('super-admin|hr-admin')
            <a href="{{ route('admin.settings.index') }}" class="underline font-medium">Admin → Settings → Attendance</a>.
        @else
            <strong>Admin → Settings → Attendance</strong>, which an administrator can do.
        @endrole
        </p>
    </div>
</div>
@endunless

{{-- ── My clock in / out ── --}}
<div class="card p-5 mb-6" x-data="officeClock()">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-xs uppercase tracking-wider text-slate-500 mb-1">Today · {{ now()->format('D d M Y') }}</p>
            <p class="text-2xl font-bold text-slate-800" x-text="time"></p>
            <p class="text-sm text-slate-500 mt-1">
                @if($myLog?->clock_in)
                    In at <strong>{{ $myLog->clock_in->format('H:i') }}</strong>
                    @if($myLog->clock_out) · Out at <strong>{{ $myLog->clock_out->format('H:i') }}</strong>
                        · {{ $myLog->hours }}h at office
                    @else · still clocked in @endif
                    @if($myLog->clock_in_offsite)
                        <span class="ml-1 px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-xs font-semibold">
                            Off-site{{ $myLog->clock_in_distance_m ? ' · '.$myLog->clock_in_distance_m.'m away' : '' }}
                        </span>
                    @endif
                @else
                    You have not clocked in at the office today.
                @endif
            </p>
        </div>

        <div class="flex items-center gap-2">
            @if(!$myLog?->clock_in)
                <form method="POST" action="{{ route('office-attendance.clock-in') }}" @submit="attachLocation($event)">
                    @csrf
                    <input type="hidden" name="lat" x-ref="lat"><input type="hidden" name="lng" x-ref="lng">
                    <button class="btn-primary"><i class="fas fa-right-to-bracket mr-1"></i> Clock In at Office</button>
                </form>
            @elseif(!$myLog->clock_out)
                <form method="POST" action="{{ route('office-attendance.clock-out') }}" @submit="attachLocation($event)">
                    @csrf
                    <input type="hidden" name="lat" x-ref="lat"><input type="hidden" name="lng" x-ref="lng">
                    <button class="inline-flex items-center gap-1 px-4 py-2 rounded-lg text-sm font-semibold bg-rose-600 text-white hover:bg-rose-700">
                        <i class="fas fa-right-from-bracket"></i> Clock Out
                    </button>
                </form>
            @else
                <span class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-50 text-emerald-700 text-sm font-semibold">
                    <i class="fas fa-circle-check"></i> Done for today
                </span>
            @endif
        </div>
    </div>
    <p class="text-xs text-slate-400 mt-3" x-show="locating">
        <i class="fas fa-location-crosshairs fa-spin mr-1"></i> Getting your location…
    </p>
</div>

{{-- ── Summary ── --}}
@if($isSupervisor)
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
    <x-stat-card icon="fas fa-users"          label="Expected"    :value="$summary['expected']"   color="blue" />
    <x-stat-card icon="fas fa-right-to-bracket" label="Clocked In" :value="$summary['clocked_in']" color="green" />
    <x-stat-card icon="fas fa-hourglass-half" label="Still In"    :value="$summary['still_in']"   color="yellow" />
    <x-stat-card icon="fas fa-user-slash"     label="No Clock-In" :value="$summary['missing']"    color="red" />
    <x-stat-card icon="fas fa-location-dot"   label="Off-site"    :value="$summary['offsite']"    color="yellow" />
</div>

<x-filter-bar :action="route('office-attendance.index')">
    <div><label class="form-label">Date</label>
        <input type="date" name="date" value="{{ $date }}" class="form-input">
    </div>
</x-filter-bar>
@endif

{{-- ── Register ── --}}
<x-data-table>
    <thead class="bg-slate-50"><tr>
        <th class="table-head px-6 py-3 text-left">Staff Member</th>
        <th class="table-head px-4 py-3 text-left">Role</th>
        <th class="table-head px-4 py-3 text-left">Clock In</th>
        <th class="table-head px-4 py-3 text-left">Clock Out</th>
        <th class="table-head px-4 py-3 text-left">Hours</th>
        <th class="table-head px-4 py-3 text-left">Location</th>
        <th class="table-head px-4 py-3 text-left">Status</th>
    </tr></thead>
    <tbody class="divide-y divide-slate-100">
        @forelse($roster as $row)
        @php $log = $row['log']; @endphp
        <tr class="table-row {{ !$log?->clock_in ? 'bg-rose-50/40' : '' }}">
            <td class="px-6 py-3 text-sm font-medium text-slate-800">{{ $row['user']->name }}</td>
            <td class="px-4 py-3 text-xs text-slate-500">{{ $row['user']->getRoleNames()->implode(', ') }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $log?->clock_in?->format('H:i') ?? '—' }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $log?->clock_out?->format('H:i') ?? '—' }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $log?->hours ? $log->hours.'h' : '—' }}</td>
            <td class="px-4 py-3 text-xs">
                @if(!$log?->clock_in)
                    <span class="text-slate-300">—</span>
                @elseif($log->clock_in_offsite)
                    <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 font-semibold">
                        Off-site{{ $log->clock_in_distance_m ? ' · '.$log->clock_in_distance_m.'m' : '' }}
                    </span>
                @elseif($log->clock_in_distance_m !== null)
                    <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-semibold">
                        At office{{ ' · '.$log->clock_in_distance_m.'m' }}
                    </span>
                @else
                    <span class="text-slate-400">No GPS</span>
                @endif
            </td>
            <td class="px-4 py-3">
                @if(!$log?->clock_in)
                    <span class="badge-red">No clock-in</span>
                @elseif($log->is_open)
                    <span class="badge-yellow">Still in</span>
                @else
                    <span class="badge-green">Complete</span>
                @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="7" class="py-12 text-center text-slate-400">Nobody is on the office register for this date.</td></tr>
        @endforelse
    </tbody>
</x-data-table>

<p class="text-xs text-slate-400 mt-4">
    <i class="fas fa-circle-info mr-1"></i>
    This register is for visibility only — it is never used to calculate salary. Client site visits are recorded separately under
    <a href="{{ route('am-visits.index') }}" class="underline">Client Site Visits</a>.
</p>

@push('scripts')
<script>
function officeClock() {
    return {
        time: new Date().toLocaleTimeString(),
        locating: false,
        init() { setInterval(() => this.time = new Date().toLocaleTimeString(), 1000); },
        // Grab GPS before submitting. If the browser refuses or takes too long
        // the form still goes through — the record matters more than the fix.
        attachLocation(e) {
            if (this.locating || !navigator.geolocation) return true;
            e.preventDefault();
            this.locating = true;
            const form = e.target;
            const done = () => { this.locating = false; form.submit(); };
            navigator.geolocation.getCurrentPosition(
                pos => {
                    this.$refs.lat.value = pos.coords.latitude;
                    this.$refs.lng.value = pos.coords.longitude;
                    done();
                },
                () => done(),
                { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
            );
        }
    }
}
</script>
@endpush
@endsection
