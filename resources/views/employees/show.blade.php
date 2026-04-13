@extends("layouts.app")
@section("title", $employee->full_name)
@section("breadcrumb")
<a href="{{ route('employees.index') }}" class="text-slate-500 hover:text-slate-700 text-sm">Employees</a>
<i class="fas fa-chevron-right text-slate-300 text-xs mx-1"></i>
<span class="text-slate-600 text-sm font-medium">{{ $employee->full_name }}</span>
@endsection
@section("content")

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    {{-- Profile Card --}}
    <div class="card p-6 text-center">
        <img src="{{ $employee->avatar_url }}" class="w-24 h-24 rounded-2xl object-cover border-4 border-blue-100 mx-auto mb-4">
        <h2 class="text-lg font-bold text-slate-900">{{ $employee->full_name }}</h2>
        <p class="text-sm text-slate-500">{{ $employee->designation?->title }}</p>
        <p class="text-xs text-slate-400 mt-1">{{ $employee->department?->name }}</p>
        <div class="mt-3">{!! $employee->status_badge !!}</div>
        <div class="mt-4 pt-4 border-t border-slate-100 space-y-2 text-left">
            <div class="flex items-center gap-2 text-sm text-slate-600">
                <i class="fas fa-id-badge w-4 text-slate-400"></i>
                <span class="font-mono text-xs">{{ $employee->emp_number }}</span>
            </div>
            <div class="flex items-center gap-2 text-sm text-slate-600">
                <i class="fas fa-envelope w-4 text-slate-400"></i>
                <span class="text-xs truncate">{{ $employee->user?->email }}</span>
            </div>
            @if($employee->phone)
            <div class="flex items-center gap-2 text-sm text-slate-600">
                <i class="fas fa-phone w-4 text-slate-400"></i>
                <span class="text-xs">{{ $employee->phone }}</span>
            </div>
            @endif
            <div class="flex items-center gap-2 text-sm text-slate-600">
                <i class="fas fa-calendar w-4 text-slate-400"></i>
                <span class="text-xs">Hired {{ $employee->hire_date?->format('M d, Y') }}</span>
            </div>
        </div>
        <div class="mt-4 flex flex-col gap-2">
            <a href="{{ route('employees.edit', $employee) }}" class="btn-primary w-full justify-center text-xs"><i class="fas fa-pen"></i> Edit Profile</a>
            <a href="{{ route('employees.documents', $employee) }}" class="btn-secondary w-full justify-center text-xs"><i class="fas fa-folder"></i> Documents</a>
        </div>
    </div>

    {{-- Details --}}
    <div class="lg:col-span-3 space-y-4">
        {{-- Tabs --}}
        <div x-data="{ tab: 'info' }" class="card">
            <div class="flex border-b border-slate-100 overflow-x-auto">
                @foreach(["info" => "Personal Info", "job" => "Job Details", "bank" => "Banking", "leave" => "Leave Balances"] as $key => $label)
                <button @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'border-b-2 border-blue-600 text-blue-600 font-semibold' : 'text-slate-500 hover:text-slate-700'" class="px-5 py-3 text-sm whitespace-nowrap transition-colors">{{ $label }}</button>
                @endforeach
            </div>

            <div class="p-6">
                {{-- Personal --}}
                <div x-show="tab === 'info'" class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    @foreach(["Date of Birth" => $employee->date_of_birth?->format('M d, Y'), "Gender" => ucfirst($employee->gender ?? '—'), "National ID" => $employee->national_id ?? '—', "Personal Email" => $employee->personal_email ?? '—', "City" => $employee->city ?? '—', "Country" => $employee->country ?? '—', "Emergency Contact" => $employee->emergency_contact_name ?? '—', "Emergency Phone" => $employee->emergency_contact_phone ?? '—'] as $label => $val)
                    <div>
                        <p class="text-xs text-slate-400 uppercase tracking-wider">{{ $label }}</p>
                        <p class="text-sm font-medium text-slate-700 mt-0.5">{{ $val }}</p>
                    </div>
                    @endforeach
                    @if($employee->address)
                    <div class="col-span-2">
                        <p class="text-xs text-slate-400 uppercase tracking-wider">Address</p>
                        <p class="text-sm font-medium text-slate-700 mt-0.5">{{ $employee->address }}</p>
                    </div>
                    @endif
                </div>

                {{-- Job --}}
                <div x-show="tab === 'job'" class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    @foreach(["Employee Number" => $employee->emp_number, "Department" => $employee->department?->name ?? '—', "Designation" => $employee->designation?->title ?? '—', "Manager" => $employee->manager?->full_name ?? '—', "Employment Type" => ucfirst(str_replace('_',' ',$employee->employment_type)), "Hire Date" => $employee->hire_date?->format('M d, Y'), "Salary Grade" => $employee->salary_grade ?? '—', "Tax Number" => $employee->tax_number ?? '—'] as $label => $val)
                    <div>
                        <p class="text-xs text-slate-400 uppercase tracking-wider">{{ $label }}</p>
                        <p class="text-sm font-medium text-slate-700 mt-0.5">{{ $val }}</p>
                    </div>
                    @endforeach
                </div>

                {{-- Banking --}}
                <div x-show="tab === 'bank'" class="grid grid-cols-2 gap-4">
                    @foreach(["Bank Name" => $employee->bank_name ?? '—', "Account Number" => $employee->bank_account ?? '—', "Branch" => $employee->bank_branch ?? '—'] as $label => $val)
                    <div>
                        <p class="text-xs text-slate-400 uppercase tracking-wider">{{ $label }}</p>
                        <p class="text-sm font-medium text-slate-700 mt-0.5">{{ $val }}</p>
                    </div>
                    @endforeach
                </div>

                {{-- Leave Balances --}}
                <div x-show="tab === 'leave'">
                    @if($employee->leaveBalances->count())
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        @foreach($employee->leaveBalances as $bal)
                        <div class="rounded-xl p-4 border border-slate-200 bg-slate-50">
                            <p class="text-xs font-semibold text-slate-500 uppercase">{{ $bal->leaveType->name }}</p>
                            <div class="flex items-end gap-2 mt-2">
                                <span class="text-2xl font-bold text-slate-900">{{ $bal->remaining }}</span>
                                <span class="text-xs text-slate-400 mb-1">/ {{ $bal->total_days }} days</span>
                            </div>
                            <div class="mt-2 bg-slate-200 rounded-full h-1.5">
                                <div class="bg-blue-500 h-1.5 rounded-full" style="width:{{ $bal->total_days > 0 ? min(100, ($bal->used_days/$bal->total_days)*100) : 0 }}%"></div>
                            </div>
                            <p class="text-xs text-slate-400 mt-1">{{ $bal->used_days }} used · {{ $bal->pending_days }} pending</p>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p class="text-sm text-slate-400 text-center py-8">No leave balances for current year.</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Employment History --}}
        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-slate-800 flex items-center gap-2"><i class="fas fa-history text-blue-500"></i> Employment History</h3>
            </div>
            @if($employee->history->count())
            <div class="relative pl-6 border-l-2 border-slate-200 space-y-4">
                @foreach($employee->history as $hist)
                <div class="relative">
                    <div class="absolute -left-[1.375rem] w-3 h-3 bg-blue-500 rounded-full border-2 border-white top-1.5"></div>
                    <p class="text-sm font-semibold text-slate-800">{{ $hist->position }}</p>
                    <p class="text-xs text-slate-500">{{ $hist->start_date?->format('M Y') }} {{ $hist->end_date ? '— ' . $hist->end_date?->format('M Y') : '— Present' }}</p>
                    @if($hist->reason_for_change)<p class="text-xs text-slate-400 mt-0.5">{{ $hist->reason_for_change }}</p>@endif
                </div>
                @endforeach
            </div>
            @else
            <p class="text-sm text-slate-400">No history available.</p>
            @endif
        </div>
    </div>
</div>
@endsection
