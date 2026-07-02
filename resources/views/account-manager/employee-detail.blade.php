@extends("layouts.app")
@section("title", $employee->full_name)
@section("content")

<x-page-header title="{{ $employee->full_name }}" subtitle="Employee Profile — {{ $employee->emp_number }}">
    <a href="{{ route('account-manager.employees') }}" class="btn-secondary text-sm"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>

@if(session('success'))
<div class="mb-4 flex items-center gap-3 px-4 py-3 bg-green-50 border border-green-200 rounded-xl text-green-700 text-sm">
    <i class="fas fa-check-circle"></i> {{ session('success') }}
</div>
@endif
@if($errors->any())
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm">
    <i class="fas fa-exclamation-circle mr-1"></i> {{ $errors->first() }}
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

    {{-- ── Left sidebar ─────────────────────────────────────────────────── --}}
    <div class="lg:col-span-1 space-y-5">

        {{-- Profile card --}}
        <div class="card p-6 text-center">
            <img src="{{ $employee->avatar_url }}" class="w-20 h-20 rounded-full mx-auto object-cover mb-3 ring-2 ring-slate-100">
            <h2 class="font-bold text-slate-800 text-base">{{ $employee->full_name }}</h2>
            <p class="text-sm text-slate-500 mt-1">{{ $employee->designation?->title ?? '—' }}</p>
            <p class="text-xs text-slate-400">{{ $employee->department?->name ?? '—' }}</p>
            @if($employeeClient)
            <div class="mt-2">
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-100">
                    <i class="fas fa-building text-indigo-400 text-xs"></i> {{ $employeeClient->company_name }}
                </span>
            </div>
            @endif
            <div class="mt-3">{!! $employee->status_badge !!}</div>
            @if($employee->is_blacklisted)
            <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700">⛔ Blacklisted</span>
            @endif
            @if($employee->on_hold)
            <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-700">⏸ On Hold</span>
            @endif
        </div>

        {{-- Quick stats --}}
        <div class="card p-4 space-y-3">
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Emp Number</span>
                <span class="font-mono font-semibold text-slate-800">{{ $employee->emp_number }}</span>
            </div>
            @if($employee->payroll_number)
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Payroll No</span>
                <span class="font-mono font-semibold text-slate-800">{{ $employee->payroll_number }}</span>
            </div>
            @endif
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Hire Date</span>
                <span class="font-medium text-slate-800">{{ $employee->hire_date?->format('d M Y') ?? '—' }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Type</span>
                <span class="font-medium text-slate-800">{{ ucfirst(str_replace('_',' ',$employee->employment_type ?? '')) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Salary</span>
                <span class="font-bold text-emerald-700">{{ $employee->salary ? 'UGX '.number_format($employee->salary->basic_salary ?? 0) : '—' }}</span>
            </div>
            @if($employee->nssf_number)
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">NSSF No</span>
                <span class="font-mono text-xs text-slate-700">{{ $employee->nssf_number }}</span>
            </div>
            @endif
            @if($employee->tin_number)
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">TIN</span>
                <span class="font-mono text-xs text-slate-700">{{ $employee->tin_number }}</span>
            </div>
            @endif
        </div>
    </div>

    {{-- ── Main area with tabs ───────────────────────────────────────────── --}}
    <div class="lg:col-span-3" x-data="{ tab: 'edit' }">

        {{-- Tab nav --}}
        <div class="flex gap-1 mb-5 bg-slate-100 p-1 rounded-xl flex-wrap">
            @foreach([
                'edit'       => ['fas fa-edit',        'Edit Profile'],
                'statutory'  => ['fas fa-file-invoice-dollar', 'Statutory & Banking'],
                'attendance' => ['fas fa-calendar-check','Attendance'],
                'documents'  => ['fas fa-folder-open', 'Documents'],
                'leaves'     => ['fas fa-umbrella-beach','Leave Balances'],
            ] as $t => [$icon, $label])
            <button @click="tab = '{{ $t }}'"
                    :class="tab === '{{ $t }}' ? 'bg-white text-slate-900 shadow' : 'text-slate-500 hover:text-slate-700'"
                    class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium transition">
                <i class="{{ $icon }} text-xs"></i> {{ $label }}
            </button>
            @endforeach
        </div>

        {{-- ── TAB: Edit Profile ─────────────────────────────────────────── --}}
        <div x-show="tab === 'edit'">
            <form method="POST" action="{{ route('account-manager.employees.update', $employee) }}" class="space-y-5">
                @csrf @method("PUT")

                {{-- Personal Info --}}
                <div class="card p-5">
                    <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Personal Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="form-label">Title</label>
                            <select name="title" class="form-input">
                                <option value="">—</option>
                                @foreach(['Mr','Mrs','Ms','Dr','Prof'] as $t)
                                <option value="{{ $t }}" {{ $employee->title === $t ? 'selected' : '' }}>{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-input" value="{{ old('first_name', $employee->first_name) }}" required>
                        </div>
                        <div>
                            <label class="form-label">Middle Name</label>
                            <input type="text" name="middle_name" class="form-input" value="{{ old('middle_name', $employee->middle_name) }}">
                        </div>
                        <div>
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-input" value="{{ old('last_name', $employee->last_name) }}" required>
                        </div>
                        <div>
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-input" value="{{ old('date_of_birth', $employee->date_of_birth?->format('Y-m-d')) }}">
                        </div>
                        <div>
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-input">
                                <option value="">—</option>
                                <option value="male"   {{ $employee->gender === 'male'   ? 'selected' : '' }}>Male</option>
                                <option value="female" {{ $employee->gender === 'female' ? 'selected' : '' }}>Female</option>
                                <option value="other"  {{ $employee->gender === 'other'  ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Marital Status</label>
                            <select name="marital_status" class="form-input">
                                <option value="">—</option>
                                @foreach(['single','married','divorced','widowed'] as $v)
                                <option value="{{ $v }}" {{ $employee->marital_status === $v ? 'selected' : '' }}>{{ ucfirst($v) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Nationality</label>
                            <input type="text" name="nationality" class="form-input" value="{{ old('nationality', $employee->nationality) }}" placeholder="Ugandan">
                        </div>
                        <div>
                            <label class="form-label">Religion</label>
                            <input type="text" name="religion" class="form-input" value="{{ old('religion', $employee->religion) }}">
                        </div>
                    </div>
                </div>

                {{-- Contact --}}
                <div class="card p-5">
                    <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Contact Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-input" value="{{ old('phone', $employee->phone) }}" placeholder="+256 700 000 000">
                        </div>
                        <div>
                            <label class="form-label">Personal Email</label>
                            <input type="email" name="personal_email" class="form-input" value="{{ old('personal_email', $employee->personal_email) }}">
                        </div>
                        <div>
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-input" value="{{ old('address', $employee->address) }}">
                        </div>
                        <div>
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-input" value="{{ old('city', $employee->city) }}">
                        </div>
                        <div>
                            <label class="form-label">Country</label>
                            <input type="text" name="country" class="form-input" value="{{ old('country', $employee->country ?? 'Uganda') }}">
                        </div>
                    </div>
                </div>

                {{-- Emergency & NOK --}}
                <div class="card p-5">
                    <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Emergency Contact &amp; Next of Kin</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Emergency Contact Name</label>
                            <input type="text" name="emergency_contact_name" class="form-input" value="{{ old('emergency_contact_name', $employee->emergency_contact_name) }}">
                        </div>
                        <div>
                            <label class="form-label">Emergency Contact Phone</label>
                            <input type="text" name="emergency_contact_phone" class="form-input" value="{{ old('emergency_contact_phone', $employee->emergency_contact_phone) }}">
                        </div>
                        <div>
                            <label class="form-label">Next of Kin Name</label>
                            <input type="text" name="next_of_kin_name" class="form-input" value="{{ old('next_of_kin_name', $employee->next_of_kin_name) }}">
                        </div>
                        <div>
                            <label class="form-label">NOK Relation</label>
                            <input type="text" name="next_of_kin_relation" class="form-input" value="{{ old('next_of_kin_relation', $employee->next_of_kin_relation) }}" placeholder="Wife / Brother / Mother">
                        </div>
                        <div>
                            <label class="form-label">NOK Phone</label>
                            <input type="text" name="next_of_kin_phone" class="form-input" value="{{ old('next_of_kin_phone', $employee->next_of_kin_phone) }}">
                        </div>
                        <div>
                            <label class="form-label">NOK Email</label>
                            <input type="email" name="next_of_kin_email" class="form-input" value="{{ old('next_of_kin_email', $employee->next_of_kin_email) }}">
                        </div>
                    </div>
                </div>

                {{-- Job & Salary --}}
                <div class="card p-5">
                    <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Job &amp; Salary</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="form-label">Status</label>
                            <select name="status" class="form-input">
                                @foreach(['active'=>'Active','on_leave'=>'On Leave','suspended'=>'Suspended','terminated'=>'Terminated'] as $v => $l)
                                <option value="{{ $v }}" {{ $employee->status === $v ? 'selected' : '' }}>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Employment Type</label>
                            <select name="employment_type" class="form-input">
                                @foreach(['full_time'=>'Full Time','part_time'=>'Part Time','contract'=>'Contract','casual'=>'Casual','intern'=>'Intern'] as $v => $l)
                                <option value="{{ $v }}" {{ $employee->employment_type === $v ? 'selected' : '' }}>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Hire Date</label>
                            <input type="date" name="hire_date" class="form-input" value="{{ old('hire_date', $employee->hire_date?->format('Y-m-d')) }}">
                        </div>
                        <div>
                            <label class="form-label">Contract End Date</label>
                            <input type="date" name="contract_end_date" class="form-input" value="{{ old('contract_end_date', $employee->contract_end_date?->format('Y-m-d')) }}">
                        </div>
                        <div>
                            <label class="form-label">Work Location / Site</label>
                            <input type="text" name="work_location" class="form-input" value="{{ old('work_location', $employee->work_location) }}">
                        </div>
                        <div>
                            <label class="form-label">Basic Salary (UGX)</label>
                            <input type="number" name="basic_salary" class="form-input" min="0" step="1"
                                   value="{{ old('basic_salary', $employee->salary?->basic_salary ?? '') }}" placeholder="e.g. 800000">
                        </div>
                        <div>
                            <label class="form-label">Salary Type</label>
                            <select name="salary_type" class="form-input">
                                @foreach(['monthly'=>'Monthly','daily'=>'Daily','hourly'=>'Hourly'] as $v => $l)
                                <option value="{{ $v }}" {{ ($employee->salary?->salary_type ?? 'monthly') === $v ? 'selected' : '' }}>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn-primary px-8">
                        <i class="fas fa-save mr-2"></i> Save All Changes
                    </button>
                </div>
            </form>
        </div>

        {{-- ── TAB: Statutory & Banking ───────────────────────────────────── --}}
        <div x-show="tab === 'statutory'">
            <form method="POST" action="{{ route('account-manager.employees.update', $employee) }}" class="space-y-5">
                @csrf @method("PUT")
                <input type="hidden" name="_tab" value="statutory">

                {{-- Statutory IDs --}}
                <div class="card p-5">
                    <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Statutory ID Numbers</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">NSSF Number</label>
                            <input type="text" name="nssf_number" class="form-input font-mono" value="{{ old('nssf_number', $employee->nssf_number) }}" placeholder="e.g. 7918000032712">
                        </div>
                        <div>
                            <label class="form-label">TIN Number</label>
                            <input type="text" name="tin_number" class="form-input font-mono" value="{{ old('tin_number', $employee->tin_number) }}" placeholder="e.g. 1001008394">
                        </div>
                        <div>
                            <label class="form-label">National ID</label>
                            <input type="text" name="national_id" class="form-input font-mono" value="{{ old('national_id', $employee->national_id) }}">
                        </div>
                        <div>
                            <label class="form-label">Passport Number</label>
                            <input type="text" name="passport_number" class="form-input" value="{{ old('passport_number', $employee->passport_number) }}">
                        </div>
                        <div>
                            <label class="form-label">IFMS Supplier No</label>
                            <input type="text" name="ifms_supplier_no" class="form-input" value="{{ old('ifms_supplier_no', $employee->ifms_supplier_no) }}">
                        </div>
                        <div>
                            <label class="form-label">Pension No</label>
                            <input type="text" name="pension_no" class="form-input" value="{{ old('pension_no', $employee->pension_no) }}">
                        </div>
                    </div>
                </div>

                {{-- Statutory Deductions --}}
                <div class="card p-5">
                    <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Statutory Deduction Settings</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        @foreach([
                            ['charge_paye',           'Charge PAYE Tax'],
                            ['charge_nssf',           'Charge NSSF (Employee)'],
                            ['nssf_paid_by_employer', 'NSSF Paid by Employer'],
                            ['charge_lst',            'Charge LST'],
                            ['tax_paid_by_employer',  'Tax Paid by Employer'],
                        ] as [$field, $label])
                        <div class="flex items-center gap-3 bg-slate-50 rounded-lg p-3">
                            <input type="checkbox" name="{{ $field }}" value="1" id="{{ $field }}"
                                   class="w-4 h-4 rounded" {{ $employee->$field ? 'checked' : '' }}>
                            <label for="{{ $field }}" class="text-sm font-medium text-slate-700">{{ $label }}</label>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Banking --}}
                <div class="card p-5">
                    <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Banking &amp; Payment</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Payment Mode</label>
                            <select name="payment_mode" class="form-input">
                                <option value="">— Select —</option>
                                @foreach(['bank_transfer'=>'Bank Transfer','mobile_money'=>'Mobile Money','cash'=>'Cash','cheque'=>'Cheque'] as $v => $l)
                                <option value="{{ $v }}" {{ $employee->payment_mode === $v ? 'selected' : '' }}>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Mobile Money Number</label>
                            <input type="text" name="mobile_money_number" class="form-input" value="{{ old('mobile_money_number', $employee->mobile_money_number) }}" placeholder="+256 77x xxx xxx">
                        </div>
                        <div>
                            <label class="form-label">Bank Name</label>
                            <input type="text" name="bank_name" class="form-input" value="{{ old('bank_name', $employee->bank_name) }}" placeholder="e.g. Stanbic Bank">
                        </div>
                        <div>
                            <label class="form-label">Bank Account Number</label>
                            <input type="text" name="bank_account" class="form-input font-mono" value="{{ old('bank_account', $employee->bank_account) }}">
                        </div>
                        <div>
                            <label class="form-label">Bank Branch</label>
                            <input type="text" name="bank_branch" class="form-input" value="{{ old('bank_branch', $employee->bank_branch) }}">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn-primary px-8">
                        <i class="fas fa-save mr-2"></i> Save Statutory &amp; Banking
                    </button>
                </div>
            </form>
        </div>

        {{-- ── TAB: Attendance ───────────────────────────────────────────── --}}
        <div x-show="tab === 'attendance'">
            <div class="card overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-800">Recent Attendance (14 days)</h3>
                </div>
                <table class="w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="table-head px-4 py-3 text-left">Date</th>
                            <th class="table-head px-4 py-3 text-left">Clock In</th>
                            <th class="table-head px-4 py-3 text-left">Clock Out</th>
                            <th class="table-head px-4 py-3 text-left">Hours</th>
                            <th class="table-head px-4 py-3 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($recentAttendance as $log)
                        <tr>
                            <td class="px-4 py-2 text-sm">{{ $log->date->format('D d M') }}</td>
                            <td class="px-4 py-2 text-sm font-mono">{{ $log->clock_in?->format('H:i') ?? '—' }}</td>
                            <td class="px-4 py-2 text-sm font-mono">{{ $log->clock_out?->format('H:i') ?? '—' }}</td>
                            <td class="px-4 py-2 text-sm">
                                @if($log->clock_in && $log->clock_out)
                                    {{ number_format($log->clock_in->diffInMinutes($log->clock_out)/60, 1) }}h
                                @else —
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold
                                    {{ $log->status === 'present' ? 'bg-green-100 text-green-700' :
                                       ($log->status === 'late'    ? 'bg-yellow-100 text-yellow-700' :
                                                                      'bg-red-100 text-red-700') }}">
                                    {{ ucfirst($log->status) }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="py-10 text-center text-slate-400 text-sm">No attendance records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── TAB: Documents ────────────────────────────────────────────── --}}
        <div x-show="tab === 'documents'">
            <div class="card overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-800">Documents ({{ $employee->documents->count() }})</h3>
                </div>
                @forelse($employee->documents as $doc)
                <div class="flex items-center justify-between px-6 py-3 border-b border-slate-50 hover:bg-slate-50">
                    <div>
                        <p class="text-sm font-medium text-slate-800">{{ $doc->title }}</p>
                        <span class="inline-block px-2 py-0.5 rounded text-xs bg-blue-50 text-blue-700 mt-1">{{ ucfirst(str_replace('_',' ',$doc->document_type)) }}</span>
                    </div>
                    <a href="{{ route('account-manager.documents.download', $doc) }}" class="btn-xs btn-blue"><i class="fas fa-download mr-1"></i> Download</a>
                </div>
                @empty
                <div class="py-12 text-center text-slate-400 text-sm">No documents uploaded.</div>
                @endforelse
            </div>
        </div>

        {{-- ── TAB: Leave Balances ────────────────────────────────────────── --}}
        <div x-show="tab === 'leaves'">
            <div class="card overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="font-semibold text-slate-800">Leave Balances</h3>
                </div>
                @forelse($leaveBalance as $bal)
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-50">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">{{ $bal->leaveType?->name ?? 'Unknown' }}</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="text-center">
                            <p class="text-xs text-slate-400">Allocated</p>
                            <p class="font-bold text-slate-700">{{ $bal->allocated }}</p>
                        </div>
                        <div class="text-center">
                            <p class="text-xs text-slate-400">Used</p>
                            <p class="font-bold text-amber-600">{{ $bal->used }}</p>
                        </div>
                        <div class="text-center">
                            <p class="text-xs text-slate-400">Remaining</p>
                            <p class="font-bold text-emerald-700">{{ $bal->allocated - $bal->used }}</p>
                        </div>
                    </div>
                </div>
                @empty
                <div class="py-12 text-center text-slate-400 text-sm">No leave balances assigned.</div>
                @endforelse
            </div>
        </div>

    </div>
</div>
@endsection
