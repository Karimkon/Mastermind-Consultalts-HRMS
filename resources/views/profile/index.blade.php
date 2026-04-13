@extends('layouts.app')
@section('title', 'My Profile')
@section('content')
<x-page-header title="My Profile" subtitle="Manage your account information"/>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6" x-data="{ activeTab: 'profile' }">
    <!-- Left sidebar -->
    <div class="space-y-4">
        <div class="card p-6 text-center">
            <div class="relative inline-block mb-4">
                <img src="{{ auth()->user()->avatar_url }}" class="w-24 h-24 rounded-2xl object-cover shadow-lg mx-auto">
                <label class="absolute -bottom-2 -right-2 w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center cursor-pointer hover:bg-blue-700 transition-colors shadow">
                    <i class="fas fa-camera text-white text-xs"></i>
                    <input type="file" class="hidden" id="avatar-upload" accept="image/*">
                </label>
            </div>
            <h3 class="font-semibold text-slate-800 text-lg">{{ auth()->user()->name }}</h3>
            <p class="text-sm text-slate-500">{{ auth()->user()->employee?->designation?->title ?? auth()->user()->roles->first()?->name }}</p>
            <p class="text-xs text-slate-400 mt-1">{{ auth()->user()->email }}</p>
        </div>
        <div class="card overflow-hidden">
            @foreach(['profile'=>'User Circle','security'=>'Shield Alt','preferences'=>'Sliders H'] as $tab => $icon)
            <button type="button" @click="activeTab = '{{ $tab }}'"
                :class="activeTab === '{{ $tab }}' ? 'bg-blue-50 text-blue-700 font-semibold border-r-2 border-blue-600' : 'text-slate-600 hover:bg-slate-50'"
                class="w-full text-left px-4 py-3 text-sm flex items-center gap-2 transition-colors">
                <i class="fas fa-{{ strtolower(str_replace(' ', '-', $icon)) }} w-4"></i>{{ ucfirst($tab) }}
            </button>
            @endforeach
        </div>
    </div>
    <!-- Right content -->
    <div class="xl:col-span-2">
        <!-- Profile Info -->
        <div x-show="activeTab === 'profile'" class="card p-6">
            <h3 class="font-semibold text-slate-700 mb-4">Profile Information</h3>
            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf @method('PUT')
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2"><label class="form-label">Full Name *</label><input type="text" name="name" class="form-input" required value="{{ old('name', auth()->user()->name) }}"></div>
                    <div class="col-span-2"><label class="form-label">Email *</label><input type="email" name="email" class="form-input" required value="{{ old('email', auth()->user()->email) }}"></div>
                </div>
                @if(auth()->user()->employee)
                <div class="pt-4 border-t border-slate-100">
                    <h4 class="text-sm font-semibold text-slate-600 mb-3">Employee Details</h4>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div><span class="text-slate-500">Employee No.</span><p class="font-medium">{{ auth()->user()->employee->emp_number }}</p></div>
                        <div><span class="text-slate-500">Department</span><p class="font-medium">{{ auth()->user()->employee->department->name ?? '—' }}</p></div>
                        <div><span class="text-slate-500">Designation</span><p class="font-medium">{{ auth()->user()->employee->designation->title ?? '—' }}</p></div>
                        <div><span class="text-slate-500">Hire Date</span><p class="font-medium">{{ auth()->user()->employee->hire_date }}</p></div>
                    </div>
                </div>
                @endif
                <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i> Update Profile</button>
            </form>
        </div>
        <!-- Security -->
        <div x-show="activeTab === 'security'" x-cloak class="card p-6">
            <h3 class="font-semibold text-slate-700 mb-4">Change Password</h3>
            <form method="POST" action="{{ route('profile.password') }}" class="space-y-4 max-w-sm">
                @csrf @method('PUT')
                <div><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-input" required></div>
                <div><label class="form-label">New Password</label><input type="password" name="password" class="form-input" required></div>
                <div><label class="form-label">Confirm New Password</label><input type="password" name="password_confirmation" class="form-input" required></div>
                <button type="submit" class="btn-primary"><i class="fas fa-lock mr-1"></i> Update Password</button>
            </form>
        </div>
        <!-- Preferences -->
        <div x-show="activeTab === 'preferences'" x-cloak class="card p-6">
            <h3 class="font-semibold text-slate-700 mb-4">Preferences</h3>
            <form method="POST" action="{{ route('profile.preferences') }}" class="space-y-4">
                @csrf @method('PUT')
                <div>
                    <label class="form-label">Email Notifications</label>
                    <div class="space-y-2 mt-1">
                        @foreach(['leave_updates' => 'Leave status updates', 'payslip_ready' => 'Payslip available', 'meeting_invites' => 'Meeting invitations', 'announcements' => 'Company announcements'] as $key => $label)
                        <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                            <input type="checkbox" name="notify_{{ $key }}" class="w-4 h-4 rounded" checked>
                            {{ $label }}
                        </label>
                        @endforeach
                    </div>
                </div>
                <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i> Save Preferences</button>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('avatar-upload').addEventListener('change', function(e) {
    if (!e.target.files[0]) return;
    const form = new FormData();
    form.append('_token', '{{ csrf_token() }}');
    form.append('_method', 'PUT');
    form.append('avatar', e.target.files[0]);
    fetch('{{ route("profile.avatar") }}', { method: 'POST', body: form })
        .then(r => r.json())
        .then(d => { if (d.url) document.querySelector('img[src*="ui-avatars"]') && location.reload(); });
});
</script>
@endpush
@endsection