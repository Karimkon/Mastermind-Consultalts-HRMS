@extends('layouts.app')
@section('title', 'My Profile')
@section('content')
<x-page-header title="My Profile" subtitle="Manage your account information"/>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6" x-data="{ activeTab: 'profile' }">
    <!-- Left sidebar -->
    <div class="space-y-4">
        <div class="card p-6 text-center">
            <div class="relative inline-block mb-4">
                <img id="avatar-image" src="{{ auth()->user()->avatar_url }}"
                     class="w-24 h-24 rounded-2xl object-cover shadow-lg mx-auto transition-opacity">
                {{-- Two inputs, not one. `capture` asks the phone for the camera
                     directly; without it the same control offers the gallery and
                     the file manager. One input cannot do both, and a
                     camera-only control is useless on a desktop. --}}
                <label for="avatar-camera"
                       class="absolute -bottom-2 -right-2 w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center cursor-pointer hover:bg-blue-700 transition-colors shadow"
                       title="Take a photo">
                    <i class="fas fa-camera text-white text-xs"></i>
                </label>
                <input type="file" class="sr-only" id="avatar-camera" accept="image/*" capture="user" data-avatar-input>
                <input type="file" class="sr-only" id="avatar-upload" accept="image/*" data-avatar-input>
            </div>

            <div class="flex items-center justify-center gap-2 mb-3">
                <label for="avatar-camera"
                       class="px-2.5 py-1.5 rounded-lg text-xs font-medium bg-slate-100 text-slate-700 hover:bg-slate-200 cursor-pointer transition-colors">
                    <i class="fas fa-camera mr-1"></i> Take a photo
                </label>
                <label for="avatar-upload"
                       class="px-2.5 py-1.5 rounded-lg text-xs font-medium bg-slate-100 text-slate-700 hover:bg-slate-200 cursor-pointer transition-colors">
                    <i class="fas fa-image mr-1"></i> Upload from device
                </label>
            </div>

            {{-- Photos may now be several megabytes, so the wait is shown. --}}
            <div id="avatar-progress" hidden class="mb-3">
                <div class="h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-full rounded-full bg-blue-600 transition-all duration-150" style="width:0%" id="avatar-bar"></div>
                </div>
                <p class="mt-1 text-[11px] text-slate-400" id="avatar-status">Uploading&hellip;</p>
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
// Both the camera input and the gallery input go through this. Bound by
// attribute rather than by id, so adding a third way to pick a picture needs no
// change here.
const AVATAR_MAX_KB = {{ \App\Support\ProfilePhoto::maxKb() }};

function uploadAvatar(input) {
    const file = input.files[0];
    if (!file) return;

    const img = document.getElementById('avatar-image');
    const previous = img ? img.src : null;
    const panel = document.getElementById('avatar-progress');
    const bar = document.getElementById('avatar-bar');
    const status = document.getElementById('avatar-status');

    function done() {
        input.value = '';            // so choosing the same file again still fires
        if (panel) panel.hidden = true;
        if (bar) bar.style.width = '0%';
    }

    function fail(message) {
        if (img) { img.src = previous; img.style.opacity = '1'; }
        done();
        alert(message || 'That photo could not be saved.');
    }

    // Refuse it here rather than pushing several megabytes up the wire first.
    if (AVATAR_MAX_KB > 0 && file.size > AVATAR_MAX_KB * 1024) {
        fail('That photo is larger than ' + Math.round(AVATAR_MAX_KB / 1024)
             + ' MB. Try again with a smaller one.');
        return;
    }

    // Show the chosen picture straight away; a phone photo takes a moment to
    // travel and the old code left the avatar looking untouched.
    if (img && window.URL && URL.createObjectURL) {
        const preview = URL.createObjectURL(file);
        img.src = preview;
        setTimeout(() => URL.revokeObjectURL(preview), 10000);
    }
    if (img) img.style.opacity = '0.4';
    if (panel) panel.hidden = false;
    if (status) status.textContent = 'Uploading…';

    const form = new FormData();
    form.append('_token', '{{ csrf_token() }}');
    // No _method here. The route is POST, and spoofing PUT sent this to a verb
    // with no route at all — a 405 that nothing surfaced, so choosing a photo
    // looked like it had simply done nothing.
    form.append('avatar', file);

    // XHR rather than fetch: fetch cannot report upload progress, and these are
    // now allowed to be large.
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '{{ route("profile.avatar") }}', true);
    xhr.setRequestHeader('Accept', 'application/json');

    xhr.upload.addEventListener('progress', function (evt) {
        if (!evt.lengthComputable || !bar) return;
        const pct = Math.round((evt.loaded / evt.total) * 100);
        bar.style.width = pct + '%';
        if (status) status.textContent = 'Uploading… ' + pct + '%';
    });

    xhr.upload.addEventListener('load', function () {
        if (bar) bar.style.width = '100%';
        if (status) status.textContent = 'Resizing on the server…';
    });

    xhr.addEventListener('load', function () {
        if (xhr.status < 200 || xhr.status >= 300) {
            let message = 'That photo could not be saved (status ' + xhr.status + ').';
            try {
                const d = JSON.parse(xhr.responseText);
                message = d.message || Object.values(d.errors || {})[0]?.[0] || message;
            } catch (e) { /* not json; keep the status message */ }
            fail(message);
            return;
        }

        let d = {};
        try { d = JSON.parse(xhr.responseText); } catch (e) { /* ignore */ }

        // Swapped in place with a cache-buster, rather than only reloading when
        // the old picture happened to be the ui-avatars placeholder — which is
        // what the previous condition actually checked.
        if (img && d.url) {
            img.src = d.url + (d.url.includes('?') ? '&' : '?') + 'v=' + Date.now();
            img.style.opacity = '1';
            done();
        } else {
            location.reload();
        }
    });

    xhr.addEventListener('error', function () { fail('The upload failed. Check your connection and try again.'); });
    xhr.addEventListener('abort', function () { fail('The upload was cancelled.'); });

    xhr.send(form);
}

document.querySelectorAll('[data-avatar-input]').forEach(function (input) {
    input.addEventListener('change', function () { uploadAvatar(this); });
});
</script>
@endpush
@endsection