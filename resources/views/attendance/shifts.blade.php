@extends("layouts.app")
@section("title","Shift Management")
@section("content")
<x-page-header title="Shift Management">
    <a href="{{ route('attendance.index') }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</x-page-header>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 card overflow-hidden">
        <table class="w-full"><thead class="bg-slate-50"><tr>
            <th class="table-head px-6 py-3 text-left">Shift Name</th>
            <th class="table-head px-4 py-3 text-left">Start</th>
            <th class="table-head px-4 py-3 text-left">End</th>
            <th class="table-head px-4 py-3 text-left">Grace</th>
            <th class="table-head px-4 py-3 text-left">Status</th>
        </tr></thead><tbody class="divide-y divide-slate-100">
        @foreach($shifts as $shift)
        <tr class="table-row"><td class="px-6 py-3 text-sm font-medium text-slate-800">{{ $shift->name }}</td><td class="px-4 py-3 text-sm text-slate-600">{{ $shift->start_time }}</td><td class="px-4 py-3 text-sm text-slate-600">{{ $shift->end_time }}</td><td class="px-4 py-3 text-sm text-slate-600">{{ $shift->grace_minutes }} min</td><td class="px-4 py-3"><span class="badge-{{ $shift->is_active?'green':'gray' }}">{{ $shift->is_active?'Active':'Inactive' }}</span></td></tr>
        @endforeach
        </tbody></table>
    </div>
    <div class="card p-6">
        <h3 class="font-semibold text-slate-800 mb-4">Create Shift</h3>
        <form method="POST" action="{{ route('attendance.shifts.store') }}" class="space-y-3">@csrf
            <div><label class="form-label">Name *</label><input type="text" name="name" class="form-input" required></div>
            <div><label class="form-label">Start Time *</label><input type="time" name="start_time" class="form-input" required></div>
            <div><label class="form-label">End Time *</label><input type="time" name="end_time" class="form-input" required></div>
            <div><label class="form-label">Grace Minutes</label><input type="number" name="grace_minutes" value="15" class="form-input" min="0"></div>
            <button type="submit" class="btn-primary w-full justify-center">Create Shift</button>
        </form>
    </div>
</div>
@endsection
