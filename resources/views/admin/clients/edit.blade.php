@extends('layouts.app')
@section('title', 'Edit Client')

@section('content')
<div class="flex items-center gap-4 mb-6">
    <a href="{{ route('admin.clients.show', $client) }}" class="btn-secondary"><i class="fas fa-arrow-left mr-2"></i>Back</a>
    <h1 class="text-2xl font-bold text-slate-800">Edit — {{ $client->company_name }}</h1>
</div>

<form method="POST" action="{{ route('admin.clients.update', $client) }}" class="max-w-2xl">
    @csrf @method('PUT')
    <div class="card p-6 space-y-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Company Name <span class="text-red-500">*</span></label>
                <input type="text" name="company_name" class="form-input" value="{{ old('company_name', $client->company_name) }}" required>
            </div>
            <div>
                <label class="form-label">Contact Person <span class="text-red-500">*</span></label>
                <input type="text" name="contact_person" class="form-input" value="{{ old('contact_person', $client->contact_person) }}" required>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Contact Phone</label>
                <input type="text" name="phone" class="form-input" value="{{ old('phone', $client->phone) }}" placeholder="+256 ...">
            </div>
            <div>
                <label class="form-label">Contact Email</label>
                <input type="email" name="client_email" class="form-input" value="{{ old('client_email', $client->email) }}">
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Industry</label>
                <input type="text" name="industry" class="form-input" value="{{ old('industry', $client->industry) }}">
            </div>
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="active"   {{ $client->status === 'active'   ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ $client->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Deployment Area</label>
                <input type="text" name="deployment_area" class="form-input" value="{{ old('deployment_area', $client->deployment_area) }}" placeholder="e.g. Kampala Central, Entebbe">
            </div>
            <div>
                <label class="form-label">Work Area / Site</label>
                <input type="text" name="work_area" class="form-input" value="{{ old('work_area', $client->work_area) }}" placeholder="e.g. Factory, Office, Warehouse">
            </div>
        </div>
        <div>
            <label class="form-label">Address</label>
            <textarea name="address" rows="2" class="form-textarea">{{ old('address', $client->address) }}</textarea>
        </div>
        <div>
            <label class="form-label">Notes</label>
            <textarea name="notes" rows="2" class="form-textarea">{{ old('notes', $client->notes) }}</textarea>
        </div>

        <div class="border-t border-slate-200 pt-5">
            <h3 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2">
                <i class="fas fa-file-contract text-blue-500"></i> Contract & Payroll Settings
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Payment Day <span class="text-slate-400 font-normal">(1–31)</span></label>
                    <input type="number" name="payment_day" min="1" max="31"
                           class="form-input" value="{{ old('payment_day', $client->payment_day) }}"
                           placeholder="e.g. 25">
                    <p class="text-xs text-slate-400 mt-1">Day of month employees are paid per contract.</p>
                </div>
                <div>
                    <label class="form-label">Geo-Fence Radius (metres)</label>
                    <input type="number" name="geo_fence_radius" min="10" max="5000"
                           class="form-input" value="{{ old('geo_fence_radius', $client->geo_fence_radius ?? 100) }}">
                </div>
            </div>
            <div class="mt-4">
                <label class="form-label">Work Site Address</label>
                <input type="text" name="work_site_address" class="form-input"
                       value="{{ old('work_site_address', $client->work_site_address) }}"
                       placeholder="e.g. Bukoto Plot 78, Kampala">
            </div>
            <div class="grid grid-cols-2 gap-4 mt-4">
                <div>
                    <label class="form-label">Latitude</label>
                    <input type="text" name="work_site_lat" id="adminSiteLat" class="form-input"
                           value="{{ old('work_site_lat', $client->work_site_lat) }}" placeholder="e.g. 0.3476">
                </div>
                <div>
                    <label class="form-label">Longitude</label>
                    <input type="text" name="work_site_lng" id="adminSiteLng" class="form-input"
                           value="{{ old('work_site_lng', $client->work_site_lng) }}" placeholder="e.g. 32.5825">
                </div>
            </div>
            <button type="button" onclick="adminDetectLocation()" class="btn-secondary text-sm mt-2">
                <i class="fas fa-crosshairs mr-1"></i> Use My Current Location
            </button>
        </div>
    </div>
    <div class="flex gap-3 mt-4">
        <button type="submit" class="btn-primary"><i class="fas fa-save mr-2"></i>Update Client</button>
        <a href="{{ route('admin.clients.show', $client) }}" class="btn-secondary">Cancel</a>
    </div>
</form>
@push('scripts')
<script>
function adminDetectLocation() {
    if (!navigator.geolocation) { alert('Geolocation not supported.'); return; }
    navigator.geolocation.getCurrentPosition(pos => {
        document.getElementById('adminSiteLat').value = pos.coords.latitude.toFixed(7);
        document.getElementById('adminSiteLng').value = pos.coords.longitude.toFixed(7);
    }, err => alert('Location error: ' + err.message));
}
</script>
@endpush
@endsection
