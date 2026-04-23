@extends('layouts.app')
@section('title', 'System Settings')
@section('content')
<x-page-header title="System Settings" subtitle="Configure HRMS preferences"/>

<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" x-data="{ activeTab: 'general' }">
    @csrf @method('PUT')
    <div class="flex gap-6">
        <!-- Tabs sidebar -->
        <div class="w-44 flex-shrink-0">
            <div class="card overflow-hidden">
                @foreach(['general'=>'Building','payroll'=>'Money Bill Wave','leave'=>'Calendar Minus','notifications'=>'Bell'] as $tab => $icon)
                <button type="button" @click="activeTab = '{{ $tab }}'"
                    :class="activeTab === '{{ $tab }}' ? 'bg-blue-50 text-blue-700 font-semibold border-r-2 border-blue-600' : 'text-slate-600 hover:bg-slate-50'"
                    class="w-full text-left px-4 py-3 text-sm flex items-center gap-2 transition-colors">
                    <i class="fas fa-{{ strtolower(str_replace(' ', '-', $icon)) }} w-4"></i>
                    {{ ucfirst($tab) }}
                </button>
                @endforeach
            </div>
        </div>
        <!-- Tab content -->
        <div class="flex-1">
            <!-- General -->
            <div x-show="activeTab === 'general'" class="card p-6 space-y-4">
                <h3 class="font-semibold text-slate-700 mb-2">General Settings</h3>
                <div><label class="form-label">Company Name</label><input type="text" name="company_name" class="form-input" value="{{ $settings['company_name'] ?? '' }}"></div>
                <div><label class="form-label">Company Email</label><input type="email" name="company_email" class="form-input" value="{{ $settings['company_email'] ?? '' }}"></div>
                <div><label class="form-label">Company Phone</label><input type="text" name="company_phone" class="form-input" value="{{ $settings['company_phone'] ?? '' }}"></div>
                <div><label class="form-label">Address</label><textarea name="company_address" rows="3" class="form-input">{{ $settings['company_address'] ?? '' }}</textarea></div>
                <div><label class="form-label">Company Logo</label><input type="file" name="company_logo" class="form-input" accept="image/*"></div>
                <div><label class="form-label">Currency Symbol</label><input type="text" name="currency_symbol" class="form-input w-24" value="{{ $settings['currency_symbol'] ?? 'KES' }}"></div>
            </div>
            <!-- Payroll -->
            <div x-show="activeTab === 'payroll'" x-cloak class="card p-6 space-y-4">
                <h3 class="font-semibold text-slate-700 mb-2">Payroll Settings</h3>
                <div><label class="form-label">Tax Rate (%)</label><input type="number" name="tax_rate" class="form-input w-32" step="0.01" value="{{ $settings['tax_rate'] ?? '30' }}"></div>
                <div><label class="form-label">NHIF Rate (%)</label><input type="number" name="nhif_rate" class="form-input w-32" step="0.01" value="{{ $settings['nhif_rate'] ?? '2.75' }}"></div>
                <div><label class="form-label">NSSF Rate (%)</label><input type="number" name="nssf_rate" class="form-input w-32" step="0.01" value="{{ $settings['nssf_rate'] ?? '6' }}"></div>
                <div><label class="form-label">Payroll Day</label><input type="number" name="payroll_day" class="form-input w-24" min="1" max="28" value="{{ $settings['payroll_day'] ?? '25' }}"></div>
            </div>
            <!-- Leave -->
            <div x-show="activeTab === 'leave'" x-cloak class="card p-6 space-y-4">
                <h3 class="font-semibold text-slate-700 mb-2">Leave Settings</h3>
                <div><label class="form-label">Leave Year Start</label>
                    <select name="leave_year_start" class="form-input w-40">
                        @for($m=1;$m<=12;$m++)<option value="{{ $m }}" @selected(($settings['leave_year_start'] ?? 1) == $m)>{{ date('F', mktime(0,0,0,$m,1)) }}</option>@endfor
                    </select>
                </div>
                <div class="flex items-center gap-3">
                    <input type="checkbox" name="carry_forward_enabled" id="carry_forward" class="w-4 h-4" @if(($settings['carry_forward_enabled'] ?? true)) checked @endif>
                    <label for="carry_forward" class="text-sm text-slate-700">Enable carry-forward of leave balances</label>
                </div>
                <div><label class="form-label">Max Carry Forward Days</label><input type="number" name="max_carry_forward" class="form-input w-24" value="{{ $settings['max_carry_forward'] ?? '10' }}"></div>
            </div>
            <!-- Attendance / Geo-Fence -->
            <div x-show="activeTab === 'attendance'" x-cloak class="card p-6 space-y-4">
                <h3 class="font-semibold text-slate-700 mb-2">Attendance & Geo-Fence Settings</h3>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-800">
                    <i class="fas fa-map-marker-alt mr-1"></i>
                    Set the office GPS coordinates below to enable geo-fenced clock-in/out. Leave blank to allow clocking from anywhere.
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Office Latitude</label>
                        <input type="text" name="office_lat" class="form-input" placeholder="e.g. -26.2041" value="{{ $settings['office_lat'] ?? '' }}">
                        <p class="text-xs text-slate-400 mt-1">Decimal degrees (negative = South)</p>
                    </div>
                    <div>
                        <label class="form-label">Office Longitude</label>
                        <input type="text" name="office_lng" class="form-input" placeholder="e.g. 28.0473" value="{{ $settings['office_lng'] ?? '' }}">
                        <p class="text-xs text-slate-400 mt-1">Decimal degrees (positive = East)</p>
                    </div>
                </div>
                <div>
                    <label class="form-label">Geo-Fence Radius (metres)</label>
                    <input type="number" name="geo_radius_meters" class="form-input w-36" min="50" max="5000" value="{{ $settings['geo_radius_meters'] ?? 100 }}">
                    <p class="text-xs text-slate-400 mt-1">Employees must be within this radius to clock in/out. Minimum 50m.</p>
                </div>
                <div class="text-xs text-slate-500 flex items-center gap-2">
                    <i class="fas fa-info-circle text-blue-400"></i>
                    To find your office coordinates: open Google Maps, right-click on your office location, and copy the coordinates shown.
                </div>
            </div>
            <!-- Notifications -->
            <div x-show="activeTab === 'notifications'" x-cloak class="card p-6 space-y-4">
                <h3 class="font-semibold text-slate-700 mb-2">Notification Settings</h3>
                @foreach(['leave_approval_notify' => 'Notify on leave approval/rejection', 'payroll_notify' => 'Notify employees on payslip generation', 'birthday_notify' => 'Send birthday notifications'] as $key => $label)
                <div class="flex items-center gap-3">
                    <input type="checkbox" name="{{ $key }}" id="{{ $key }}" class="w-4 h-4" @if($settings[$key] ?? true) checked @endif>
                    <label for="{{ $key }}" class="text-sm text-slate-700">{{ $label }}</label>
                </div>
                @endforeach
            </div>
            <div class="mt-4">
                <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i> Save Settings</button>
            </div>
        </div>
    </div>
</form>
@endsection