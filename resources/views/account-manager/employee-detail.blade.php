@extends("layouts.app")
@section("title", $employee->full_name)
@section("content")
<x-page-header title="{{ $employee->full_name }}" subtitle="Employee Profile — {{ $employee->emp_number }}">
    <a href="{{ route('account-manager.employees') }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</x-page-header>
<x-alert/>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Profile Card --}}
    <div class="lg:col-span-1 space-y-6">
        <div class="card p-6 text-center">
            <img src="{{ $employee->avatar_url }}" class="w-20 h-20 rounded-full mx-auto object-cover mb-3">
            <h2 class="font-bold text-slate-800">{{ $employee->full_name }}</h2>
            <p class="text-sm text-slate-500">{{ $employee->designation?->title }}</p>
            <p class="text-sm text-slate-500">{{ $employee->department?->name }}</p>
            <div class="mt-3">{!! $employee->status_badge !!}</div>
        </div>
        <div class="card p-6">
            <h3 class="font-semibold text-slate-700 mb-4">Contact & Details</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Email</dt><dd class="font-medium">{{ $employee->user?->email }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Phone</dt><dd class="font-medium">{{ $employee->phone ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Hired</dt><dd class="font-medium">{{ $employee->hire_date?->format('d M Y') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Type</dt><dd class="font-medium">{{ ucfirst(str_replace('_',' ',$employee->employment_type)) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Salary</dt><dd class="font-medium">{{ $employee->salary ? 'R '.number_format($employee->salary->gross_salary ?? 0,0) : '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Next of Kin</dt><dd class="font-medium">{{ $employee->next_of_kin_name ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">NOK Phone</dt><dd class="font-medium">{{ $employee->next_of_kin_phone ?? '—' }}</dd></div>
            </dl>
        </div>
        <div class="card p-6">
            <h3 class="font-semibold text-slate-700 mb-4">Update Profile</h3>
            <form method="POST" action="{{ route('account-manager.employees.update', $employee) }}" class="space-y-3">
                @csrf @method("PUT")
                <div><label class="form-label">Phone</label><input type="text" name="phone" class="form-input" value="{{ $employee->phone }}"></div>
                <div><label class="form-label">Address</label><input type="text" name="address" class="form-input" value="{{ $employee->address }}"></div>
                <div><label class="form-label">Emergency Contact</label><input type="text" name="emergency_contact_name" class="form-input" value="{{ $employee->emergency_contact_name }}"></div>
                <div><label class="form-label">Emergency Phone</label><input type="text" name="emergency_contact_phone" class="form-input" value="{{ $employee->emergency_contact_phone }}"></div>
                <div><label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        @foreach(['active'=>'Active','on_leave'=>'On Leave','suspended'=>'Suspended'] as $v=>$l)
                        <option value="{{ $v }}" {{ $employee->status==$v?'selected':'' }}>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-primary w-full justify-center"><i class="fas fa-save"></i> Save</button>
            </form>
        </div>
    </div>
    <div class="lg:col-span-2 space-y-6">
        <div class="card overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100"><h3 class="font-semibold text-slate-800">Recent Attendance (14 days)</h3></div>
            <table class="w-full">
                <thead class="bg-slate-50"><tr>
                    <th class="table-head px-4 py-3 text-left">Date</th>
                    <th class="table-head px-4 py-3 text-left">In</th>
                    <th class="table-head px-4 py-3 text-left">Out</th>
                    <th class="table-head px-4 py-3 text-left">Hours</th>
                    <th class="table-head px-4 py-3 text-left">Status</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($recentAttendance as $log)
                    <tr>
                        <td class="px-4 py-2 text-sm">{{ $log->date->format('d M') }}</td>
                        <td class="px-4 py-2 text-sm">{{ $log->clock_in?->format('H:i') ?? '—' }}</td>
                        <td class="px-4 py-2 text-sm">{{ $log->clock_out?->format('H:i') ?? '—' }}</td>
                        <td class="px-4 py-2 text-sm">@if($log->clock_in && $log->clock_out){{ number_format($log->clock_in->diffInHours($log->clock_out),1) }}h@else—@endif</td>
                        <td class="px-4 py-2"><span class="badge-{{ $log->status==='present'?'green':($log->status==='late'?'yellow':'red') }}">{{ ucfirst($log->status) }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="py-6 text-center text-slate-400 text-sm">No records.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100"><h3 class="font-semibold text-slate-800">Documents ({{ $employee->documents->count() }})</h3></div>
            @forelse($employee->documents as $doc)
            <div class="flex items-center justify-between px-6 py-3 border-b border-slate-50 hover:bg-slate-50">
                <div>
                    <p class="text-sm font-medium text-slate-800">{{ $doc->title }}</p>
                    <span class="badge-blue text-xs">{{ ucfirst(str_replace('_',' ',$doc->document_type)) }}</span>
                </div>
                <a href="{{ route('account-manager.documents.download', $doc) }}" class="btn-xs btn-blue"><i class="fas fa-download"></i> Download</a>
            </div>
            @empty
            <div class="py-6 text-center text-slate-400 text-sm">No documents uploaded.</div>
            @endforelse
        </div>
        <div class="card overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100"><h3 class="font-semibold text-slate-800">Leave Balances</h3></div>
            @forelse($leaveBalance as $bal)
            <div class="flex items-center justify-between px-6 py-3 border-b border-slate-50">
                <span class="text-sm text-slate-700">{{ $bal->leaveType?->name ?? 'Unknown' }}</span>
                <span class="text-sm font-medium text-slate-700">{{ $bal->used }}/{{ $bal->allocated }} days used</span>
            </div>
            @empty
            <div class="py-6 text-center text-slate-400 text-sm">No leave balances.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection