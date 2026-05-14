@extends('layouts.app')
@section('title', 'My Client Visits')
@section('content')
<x-page-header title="Client Site Visits">
    <span class="text-sm text-slate-500">Track your presence at client work sites</span>
</x-page-header>

@if(session('success'))
<div class="alert-success mb-4"><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert-error mb-4"><i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ── LEFT COLUMN: Clock In + Active Sessions ── --}}
    <div class="space-y-4">

        {{-- Active Sessions --}}
        @if($activeSessions->count())
        <div class="card p-4 border-l-4 border-amber-500">
            <h3 class="font-bold text-amber-700 mb-3 flex items-center gap-2">
                <i class="fas fa-circle text-amber-500 text-xs animate-pulse"></i>
                Active Sessions ({{ $activeSessions->count() }})
            </h3>
            @foreach($activeSessions as $active)
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-3">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <p class="font-semibold text-slate-800 text-sm">{{ $active->client?->company_name }}</p>
                        <p class="text-xs text-slate-500">Clocked in: {{ $active->clocked_in_at->format('H:i') }}</p>
                        @if($active->site_address)
                        <p class="text-xs text-slate-500 mt-0.5"><i class="fas fa-map-marker-alt mr-1"></i>{{ $active->site_address }}</p>
                        @endif
                    </div>
                    <span class="bg-amber-100 text-amber-700 text-xs font-bold px-2 py-1 rounded-full">LIVE</span>
                </div>
                <form method="POST" action="{{ route('am-visits.clock-out') }}" class="clock-out-form">
                    @csrf
                    <input type="hidden" name="session_id" value="{{ $active->id }}">
                    <input type="hidden" name="lat" class="out-lat-field">
                    <input type="hidden" name="lng" class="out-lng-field">
                    <button type="button" class="btn-danger w-full justify-center text-sm get-location-out"
                            data-form-id="form-out-{{ $active->id }}">
                        <i class="fas fa-sign-out-alt mr-1"></i> Clock Out
                    </button>
                </form>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Clock In Card --}}
        <div class="card p-5">
            <h3 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
                <i class="fas fa-map-marker-alt text-blue-500"></i> Clock In at Client Site
            </h3>

            <form method="POST" action="{{ route('am-visits.clock-in') }}" id="clockInForm">
                @csrf
                <div class="mb-3">
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Select Client Company</label>
                    <select name="client_id" class="form-input" required id="clientSelect">
                        <option value="">— Choose a client —</option>
                        @foreach($clients as $client)
                        <option value="{{ $client->id }}"
                                data-lat="{{ $client->work_site_lat }}"
                                data-lng="{{ $client->work_site_lng }}"
                                data-radius="{{ $client->geo_fence_radius }}"
                                data-address="{{ $client->work_site_address }}">
                            {{ $client->company_name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Site info preview --}}
                <div id="sitePreview" class="hidden bg-blue-50 border border-blue-200 rounded-lg p-3 mb-3 text-sm">
                    <p class="font-semibold text-blue-700 mb-1"><i class="fas fa-building mr-1"></i><span id="siteName"></span></p>
                    <p class="text-blue-600 text-xs" id="siteAddress"></p>
                    <p class="text-blue-600 text-xs mt-1"><i class="fas fa-circle-notch mr-1"></i>Geo-fence radius: <strong id="siteRadius"></strong>m</p>
                    <p id="noAddressWarning" class="hidden text-amber-600 text-xs mt-1 font-medium">
                        <i class="fas fa-exclamation-triangle mr-1"></i>No work site address — ask your admin to configure it.
                    </p>
                </div>

                <input type="hidden" name="lat" id="clockInLat">
                <input type="hidden" name="lng" id="clockInLng">

                {{-- Location status --}}
                <div id="locationStatus" class="mb-3 hidden text-sm rounded-lg p-2 flex items-center gap-2">
                    <i class="fas fa-spinner fa-spin text-blue-500" id="locSpinner"></i>
                    <i class="fas fa-check-circle text-green-500 hidden" id="locOk"></i>
                    <i class="fas fa-times-circle text-red-500 hidden" id="locError"></i>
                    <span id="locationText" class="text-slate-600"></span>
                </div>

                <div class="mb-3">
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Notes (optional)</label>
                    <input type="text" name="notes" class="form-input" placeholder="e.g. Weekly check-in visit">
                </div>

                <button type="button" id="btnGetLocation" class="btn-secondary w-full justify-center mb-2">
                    <i class="fas fa-crosshairs mr-1"></i> Capture My Location (optional)
                </button>
                <button type="submit" id="btnClockIn" class="btn-primary w-full justify-center" disabled>
                    <i class="fas fa-sign-in-alt mr-1"></i> Clock In
                </button>
                <p class="text-xs text-center mt-2" id="clockInHint" style="color:#94a3b8;">Select a client above to enable Clock In</p>
            </form>
        </div>

        {{-- Today's Summary --}}
        <div class="card p-4">
            <h4 class="font-semibold text-slate-700 mb-3 text-sm uppercase tracking-wide">Today's Summary</h4>
            @php
            $todaySessions = $sessions->getCollection()->where('clocked_in_at', '>=', today()->startOfDay());
            $totalHours = $todaySessions->sum(fn($s) => $s->duration_hours ?? 0);
            @endphp
            <div class="grid grid-cols-2 gap-3 text-center">
                <div class="bg-slate-50 rounded-lg p-3">
                    <p class="text-2xl font-bold text-blue-600">{{ $todaySessions->count() }}</p>
                    <p class="text-xs text-slate-500">Sites Visited</p>
                </div>
                <div class="bg-slate-50 rounded-lg p-3">
                    <p class="text-2xl font-bold text-green-600">{{ number_format($totalHours, 1) }}h</p>
                    <p class="text-xs text-slate-500">Total Time</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ── RIGHT COLUMN: Sessions History ── --}}
    <div class="lg:col-span-2">
        <div class="card">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-bold text-slate-800">Visit History</h3>
                <span class="text-xs text-slate-500">{{ $sessions->total() }} total sessions</span>
            </div>
            @if($sessions->count())
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wider">
                            <th class="px-4 py-3 text-left">Client / Site</th>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-center">In</th>
                            <th class="px-4 py-3 text-center">Out</th>
                            <th class="px-4 py-3 text-center">Duration</th>
                            <th class="px-4 py-3 text-center">Location</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach($sessions as $s)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-800">{{ $s->client?->company_name ?? '—' }}</p>
                                @if($s->site_address)
                                <p class="text-xs text-slate-500"><i class="fas fa-map-pin mr-1"></i>{{ $s->site_address }}</p>
                                @endif
                                @if($s->notes)
                                <p class="text-xs text-slate-400 italic">{{ $s->notes }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $s->clocked_in_at->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-center font-mono text-green-700 font-semibold">{{ $s->clocked_in_at->format('H:i') }}</td>
                            <td class="px-4 py-3 text-center font-mono text-red-600 font-semibold">
                                {{ $s->clocked_out_at ? $s->clocked_out_at->format('H:i') : '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($s->duration_hours !== null)
                                <span class="bg-blue-50 text-blue-700 font-semibold px-2 py-0.5 rounded text-xs">{{ $s->duration_hours }}h</span>
                                @elseif(!$s->clocked_out_at)
                                <span class="badge-yellow text-xs">Active</span>
                                @else
                                <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($s->lat_in && $s->lng_in)
                                <a href="https://www.google.com/maps?q={{ $s->lat_in }},{{ $s->lng_in }}" target="_blank"
                                   class="text-blue-500 hover:text-blue-700 text-xs" title="View on map">
                                    <i class="fas fa-map-marked-alt text-base"></i>
                                </a>
                                @else
                                <span class="text-slate-300 text-xs"><i class="fas fa-map-marked-alt"></i></span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-4 border-t border-slate-100">
                {{ $sessions->links() }}
            </div>
            @else
            <div class="py-16 text-center text-slate-400">
                <i class="fas fa-route text-4xl mb-3"></i>
                <p class="font-semibold">No visit sessions yet</p>
                <p class="text-sm">Clock in at a client site to start tracking your visits.</p>
            </div>
            @endif
        </div>
    </div>
</div>

<script>
let capturedLat = null, capturedLng = null;

const clientSelect = document.getElementById('clientSelect');
const sitePreview  = document.getElementById('sitePreview');
const btnGetLoc    = document.getElementById('btnGetLocation');
const btnClockIn   = document.getElementById('btnClockIn');
const latInput     = document.getElementById('clockInLat');
const lngInput     = document.getElementById('clockInLng');
const locStatus    = document.getElementById('locationStatus');
const locText      = document.getElementById('locationText');
const locSpinner   = document.getElementById('locSpinner');
const locOk        = document.getElementById('locOk');
const locError     = document.getElementById('locError');
const clockInHint  = document.getElementById('clockInHint');

function showSitePreview(opt) {
    if (opt && opt.value) {
        document.getElementById('siteName').textContent   = opt.text;
        const addr = opt.dataset.address;
        const lat  = opt.dataset.lat;
        const lng  = opt.dataset.lng;
        if (addr) {
            document.getElementById('siteAddress').textContent = addr;
        } else if (lat && lng) {
            document.getElementById('siteAddress').textContent = `GPS: ${parseFloat(lat).toFixed(6)}, ${parseFloat(lng).toFixed(6)}`;
        } else {
            document.getElementById('siteAddress').textContent = '';
        }
        document.getElementById('siteRadius').textContent = opt.dataset.radius || 100;
        const noAddrWarn = document.getElementById('noAddressWarning');
        if (!addr && !lat) noAddrWarn.classList.remove('hidden');
        else noAddrWarn.classList.add('hidden');
        sitePreview.classList.remove('hidden');
    } else {
        sitePreview.classList.add('hidden');
    }
}

function enableClockIn() {
    if (clientSelect.value) {
        btnClockIn.disabled = false;
        if (!capturedLat) {
            clockInHint.textContent = 'Ready — GPS location not captured (optional)';
            clockInHint.style.color = '#f59e0b';
        }
    }
}

clientSelect.addEventListener('change', function() {
    showSitePreview(this.options[this.selectedIndex]);
    capturedLat = null; capturedLng = null;
    latInput.value = ''; lngInput.value = '';
    locStatus.classList.add('hidden');
    if (this.value) {
        enableClockIn();
    } else {
        btnClockIn.disabled = true;
        clockInHint.textContent = 'Select a client above to enable Clock In';
        clockInHint.style.color = '#94a3b8';
    }
});

// Auto-select if only one client
if (clientSelect.options.length === 2) {
    clientSelect.selectedIndex = 1;
    showSitePreview(clientSelect.options[1]);
    enableClockIn();
}

btnGetLoc.addEventListener('click', function() {
    if (!clientSelect.value) {
        alert('Please select a client first.');
        return;
    }
    locStatus.classList.remove('hidden');
    locSpinner.classList.remove('hidden');
    locOk.classList.add('hidden');
    locError.classList.add('hidden');
    locText.textContent = 'Getting your location…';
    clockInHint.textContent = 'Getting your location…';
    clockInHint.style.color = '#94a3b8';

    if (!navigator.geolocation) {
        locStatus.classList.add('hidden');
        clockInHint.textContent = 'GPS not supported — you can still clock in without it.';
        clockInHint.style.color = '#f59e0b';
        return;
    }

    navigator.geolocation.getCurrentPosition(
        function(pos) {
            capturedLat = pos.coords.latitude;
            capturedLng = pos.coords.longitude;
            latInput.value = capturedLat;
            lngInput.value = capturedLng;
            locSpinner.classList.add('hidden');
            locOk.classList.remove('hidden');
            locText.textContent = `Location captured (±${Math.round(pos.coords.accuracy)}m accuracy)`;
            clockInHint.textContent = '✓ GPS captured — ready to Clock In';
            clockInHint.style.color = '#16a34a';
            btnClockIn.disabled = false;
        },
        function(err) {
            locSpinner.classList.add('hidden');
            locError.classList.remove('hidden');
            locText.textContent = 'GPS unavailable — you can still clock in without it.';
            clockInHint.textContent = 'GPS unavailable — clock in without location or check browser permissions.';
            clockInHint.style.color = '#f59e0b';
            btnClockIn.disabled = false;
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
});

function showLocError(msg) {
    locSpinner.classList.add('hidden');
    locError.classList.remove('hidden');
    locText.textContent = msg;
}

// Clock-out buttons — get location before submitting
document.querySelectorAll('.get-location-out').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const form = btn.closest('form');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Getting location…';

        if (!navigator.geolocation) {
            form.submit();
            return;
        }
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                form.querySelector('.out-lat-field').value = pos.coords.latitude;
                form.querySelector('.out-lng-field').value = pos.coords.longitude;
                form.submit();
            },
            function() { form.submit(); }, // Submit even without location
            { enableHighAccuracy: true, timeout: 8000 }
        );
    });
});
</script>
@endsection
