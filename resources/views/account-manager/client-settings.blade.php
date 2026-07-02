@extends("layouts.app")
@section("title", $client->company_name . ' — Settings')
@section("content")

<x-page-header :title="$client->company_name . ' — Contract Settings'" subtitle="Configure payment schedule, payroll formula and work-site geo-fence">
    <a href="{{ route('account-manager.dashboard') }}" class="btn-secondary text-sm">
        <i class="fas fa-arrow-left mr-1"></i> Back
    </a>
</x-page-header>
<x-alert/>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    {{-- Payroll / Contract Settings --}}
    <div class="card p-6">
        <h3 class="text-base font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-file-contract text-blue-500"></i> Contract & Payroll Settings
        </h3>
        <form method="POST" action="{{ route('account-manager.client.settings.update', $client) }}" class="space-y-4">
            @csrf @method('PUT')

            <div>
                <label class="form-label">Salary Payment Day <span class="text-slate-400 font-normal">(day of month)</span></label>
                <input type="number" name="payment_day" value="{{ old('payment_day', $client->payment_day) }}"
                       class="form-input w-32" min="1" max="31" placeholder="e.g. 25">
                <p class="text-xs text-slate-400 mt-1">The day of each month when employees under this client are paid.</p>
            </div>

            <div>
                <label class="form-label">Work Site Address</label>
                <input type="text" name="work_site_address" value="{{ old('work_site_address', $client->work_site_address) }}"
                       class="form-input" placeholder="e.g. Bukoto Plot 78, Kampala">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Latitude</label>
                    <input type="text" name="work_site_lat" id="siteLat"
                           value="{{ old('work_site_lat', $client->work_site_lat) }}"
                           class="form-input" placeholder="e.g. 0.3476">
                </div>
                <div>
                    <label class="form-label">Longitude</label>
                    <input type="text" name="work_site_lng" id="siteLng"
                           value="{{ old('work_site_lng', $client->work_site_lng) }}"
                           class="form-input" placeholder="e.g. 32.5825">
                </div>
            </div>

            <div>
                <button type="button" onclick="detectLocation()"
                        class="btn-secondary text-sm mb-1">
                    <i class="fas fa-crosshairs mr-1"></i> Use My Current Location
                </button>
                <p class="text-xs text-slate-400">Click to auto-fill lat/lng from your browser's GPS (be at the work site first).</p>
            </div>

            <div>
                <label class="form-label">Geo-Fence Radius (metres)</label>
                <input type="number" name="geo_fence_radius" value="{{ old('geo_fence_radius', $client->geo_fence_radius ?? 100) }}"
                       class="form-input w-40" min="10" max="5000">
                <p class="text-xs text-slate-400 mt-1">Employees must be within this distance to clock in/out.</p>
            </div>

            <hr class="border-slate-100 my-2">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Payroll Formula Settings</p>

            <div>
                <label class="form-label">Payroll Type</label>
                <select name="payroll_type" class="form-input">
                    <option value="daily"   {{ ($client->payroll_type ?? 'daily') === 'daily'   ? 'selected' : '' }}>Daily (Casual)</option>
                    <option value="hourly"  {{ ($client->payroll_type ?? '') === 'hourly'  ? 'selected' : '' }}>Hourly (Casual)</option>
                    <option value="monthly" {{ ($client->payroll_type ?? '') === 'monthly' ? 'selected' : '' }}>Monthly (Contract)</option>
                    <option value="mixed"   {{ ($client->payroll_type ?? '') === 'mixed'   ? 'selected' : '' }}>Mixed (Casual + Contract)</option>
                </select>
            </div>

            <div class="flex items-center gap-3 mt-2">
                <input type="checkbox" name="gross_up_paye" id="grossUp" value="1"
                       class="w-4 h-4 rounded" {{ $client->gross_up_paye ? 'checked' : '' }}>
                <label for="grossUp" class="text-sm text-slate-700">
                    <span class="font-semibold">Gross-Up PAYE &amp; NSSF</span>
                    <span class="block text-xs text-slate-400">Employee receives daily-rate × days as take-home; PAYE/NSSF added on top</span>
                </label>
            </div>

            <div class="grid grid-cols-2 gap-4 mt-2">
                <div>
                    <label class="form-label">GPA/WMC Rate (%)</label>
                    <input type="number" step="0.01" name="gpa_wmc_rate"
                           value="{{ old('gpa_wmc_rate', $client->gpa_wmc_rate ?? 0) }}"
                           class="form-input" placeholder="e.g. 2.0">
                    <p class="text-xs text-slate-400 mt-1">Group Personal Accident insurance (employer cost only)</p>
                </div>
                <div>
                    <label class="form-label">Client Billing Multiplier</label>
                    <input type="number" step="0.01" name="billing_rate_multiplier"
                           value="{{ old('billing_rate_multiplier', $client->billing_rate_multiplier ?? 1.0) }}"
                           class="form-input" placeholder="e.g. 1.25">
                    <p class="text-xs text-slate-400 mt-1">Rate charged to client vs employee rate (e.g. 1.25 = 25% markup)</p>
                </div>
            </div>

            <button type="submit" class="btn-primary">
                <i class="fas fa-save mr-1"></i> Save Settings
            </button>
        </form>
    </div>

    {{-- Preview card --}}
    <div class="card p-6">
        <h3 class="text-base font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-info-circle text-slate-400"></i> Current Configuration
        </h3>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between py-2 border-b border-slate-100">
                <dt class="text-slate-500">Company</dt>
                <dd class="font-medium text-slate-800">{{ $client->company_name }}</dd>
            </div>
            <div class="flex justify-between py-2 border-b border-slate-100">
                <dt class="text-slate-500">Payment Day</dt>
                <dd class="font-medium text-slate-800">
                    {{ $client->payment_day ? $client->payment_day . ordinal_suffix($client->payment_day) . ' of month' : '—' }}
                </dd>
            </div>
            <div class="flex justify-between py-2 border-b border-slate-100">
                <dt class="text-slate-500">Work Site</dt>
                <dd class="font-medium text-slate-800 text-right max-w-xs">{{ $client->work_site_address ?: '—' }}</dd>
            </div>
            <div class="flex justify-between py-2 border-b border-slate-100">
                <dt class="text-slate-500">Coordinates</dt>
                <dd class="font-mono text-xs text-slate-700">
                    {{ $client->work_site_lat ? $client->work_site_lat . ', ' . $client->work_site_lng : '—' }}
                </dd>
            </div>
            <div class="flex justify-between py-2">
                <dt class="text-slate-500">Geo-Fence</dt>
                <dd class="font-medium text-slate-800">{{ $client->geo_fence_radius ?? 100 }} metres</dd>
            </div>
        </dl>

        @if($client->work_site_lat && $client->work_site_lng)
        <div class="mt-4 p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-700">
            <i class="fas fa-map-pin mr-1"></i>
            Geo-fence is active. Employees must be within <strong>{{ $client->geo_fence_radius ?? 100 }}m</strong> of the work site to clock in.
        </div>
        @else
        <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-700">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            No work site coordinates set yet. Set lat/lng to enable geo-fence enforcement.
        </div>
        @endif
    </div>

</div>

@push('scripts')
<script>
function detectLocation() {
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser.');
        return;
    }
    navigator.geolocation.getCurrentPosition(
        pos => {
            document.getElementById('siteLat').value = pos.coords.latitude.toFixed(7);
            document.getElementById('siteLng').value = pos.coords.longitude.toFixed(7);
        },
        err => alert('Could not get location: ' + err.message)
    );
}
</script>
@endpush
@endsection

@php
function ordinal_suffix($n) {
    $s = ['th','st','nd','rd'];
    $v = $n % 100;
    return isset($s[$v-10]) ? $s[0] : (isset($s[$v]) ? $s[$v] : $s[0]);
}
@endphp
