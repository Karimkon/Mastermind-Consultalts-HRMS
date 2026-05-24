@extends('layouts.app')
@section('title', 'Employee Central — ' . $employee->full_name)
@section('content')

<x-page-header title="Employee Central" :subtitle="$employee->emp_number . ' — ' . $employee->full_name">
    <a href="{{ route('employees.show', $employee) }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>

@if($errors->any())
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
    <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

@if(session('success'))
<div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm flex items-center gap-2">
    <i class="fas fa-check-circle"></i> {{ session('success') }}
</div>
@endif

<div x-data="{ tab: '{{ old('_tab', 'basic') }}' }" class="flex gap-6">

    {{-- ── Sidebar: avatar + tabs ──────────────────────────────── --}}
    <div class="w-52 flex-shrink-0 space-y-4">
        <div class="card p-4 text-center">
            <img src="{{ $employee->avatar_url }}" alt="{{ $employee->full_name }}"
                 class="w-16 h-16 rounded-full mx-auto object-cover mb-2 ring-2 ring-blue-100">
            <p class="font-semibold text-slate-800 text-sm leading-snug">{{ $employee->full_name }}</p>
            <p class="text-xs text-slate-400 mt-0.5">{{ $employee->emp_number }}</p>
            @if($employee->payroll_number)
            <p class="text-xs text-slate-400">{{ $employee->payroll_number }}</p>
            @endif
            <span class="mt-1 inline-block text-xs font-medium px-2 py-0.5 rounded-full
                {{ $employee->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                {{ ucfirst(str_replace('_',' ',$employee->status)) }}
            </span>
        </div>
        <div class="card overflow-hidden text-sm">
            @foreach([
                'basic'       => ['fa-id-card',    'Basic Info'],
                'placement'   => ['fa-sitemap',    'Placement'],
                'personal'    => ['fa-user',        'Personal'],
                'statutory'   => ['fa-shield-alt', 'Statutory'],
                'salary'      => ['fa-coins',       'Salary & Funds'],
                'status'      => ['fa-flag',        'Status & Flags'],
            ] as $key => [$icon, $label])
            <button type="button" @click="tab='{{ $key }}'"
                :class="tab==='{{ $key }}' ? 'bg-blue-50 text-blue-700 font-semibold border-r-2 border-blue-600' : 'text-slate-600 hover:bg-slate-50'"
                class="w-full text-left px-4 py-2.5 flex items-center gap-2 transition-colors">
                <i class="fas fa-{{ $icon }} w-4 text-xs"></i> {{ $label }}
            </button>
            @endforeach
        </div>
    </div>

    {{-- ── Main form ───────────────────────────────────────────── --}}
    <form method="POST" action="{{ route('employees.update', $employee) }}" enctype="multipart/form-data" class="flex-1 min-w-0">
        @csrf @method('PUT')
        <input type="hidden" name="_tab" :value="tab">

        {{-- ════════════════════════════════════════════════════════
             TAB 1 — BASIC INFO
        ════════════════════════════════════════════════════════ --}}
        <div x-show="tab==='basic'" class="space-y-5">

            {{-- Photo & Identity --}}
            <div class="card p-6">
                <h3 class="section-title"><i class="fas fa-id-card text-blue-500"></i> Identity & Employment</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                    <div>
                        <label class="form-label">Title</label>
                        <select name="title" class="form-input">
                            <option value="">—</option>
                            @foreach(['Mr','Mrs','Miss','Ms','Dr','Prof','Eng','Rev'] as $t)
                            <option value="{{ $t }}" @selected(old('title',$employee->title)===$t)>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">First Name <span class="text-red-500">*</span></label>
                        <input type="text" name="first_name" class="form-input" required
                               value="{{ old('first_name', $employee->first_name) }}">
                    </div>
                    <div>
                        <label class="form-label">Middle Name</label>
                        <input type="text" name="middle_name" class="form-input"
                               value="{{ old('middle_name', $employee->middle_name) }}">
                    </div>
                    <div>
                        <label class="form-label">Last Name <span class="text-red-500">*</span></label>
                        <input type="text" name="last_name" class="form-input" required
                               value="{{ old('last_name', $employee->last_name) }}">
                    </div>
                    <div>
                        <label class="form-label">Employee No.</label>
                        <input type="text" class="form-input bg-slate-50 text-slate-500" readonly
                               value="{{ $employee->emp_number }}">
                    </div>
                    <div>
                        <label class="form-label">Payroll No.</label>
                        <input type="text" name="payroll_number" class="form-input"
                               value="{{ old('payroll_number', $employee->payroll_number) }}">
                    </div>
                    <div>
                        <label class="form-label">Employment Type</label>
                        <select name="employment_type" class="form-input">
                            @foreach(['full_time'=>'Full Time','part_time'=>'Part Time','contract'=>'Contract','intern'=>'Intern','casual'=>'Casual'] as $v=>$l)
                            <option value="{{ $v }}" @selected(old('employment_type',$employee->employment_type)===$v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Date of Joining</label>
                        <input type="date" name="hire_date" class="form-input"
                               value="{{ old('hire_date', $employee->hire_date?->format('Y-m-d')) }}">
                    </div>
                    <div>
                        <label class="form-label">Profile Photo</label>
                        <input type="file" name="photo" accept="image/*"
                               class="block w-full text-sm text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-medium file:bg-blue-50 file:text-blue-700 border border-slate-200 rounded-lg p-1 cursor-pointer">
                    </div>
                </div>
            </div>

            {{-- Job Profile --}}
            <div class="card p-6">
                <h3 class="section-title"><i class="fas fa-briefcase text-indigo-500"></i> Job Profile</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
                    <div>
                        <label class="form-label">Week Off Type</label>
                        <select name="week_off_type" class="form-input">
                            <option value="">— Select —</option>
                            <option value="day_wise" @selected(old('week_off_type',$employee->week_off_type)==='day_wise')>Day Wise</option>
                            <option value="roster"   @selected(old('week_off_type',$employee->week_off_type)==='roster')>Roster</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Week Off Day</label>
                        <select name="week_off_day" class="form-input">
                            <option value="">— Select —</option>
                            @foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $d)
                            <option value="{{ $d }}" @selected(old('week_off_day',$employee->week_off_day)===$d)>{{ $d }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Holiday Calendar</label>
                        <input type="text" name="holiday_calendar" class="form-input" placeholder="e.g. Uganda Public Holidays"
                               value="{{ old('holiday_calendar', $employee->holiday_calendar) }}">
                    </div>
                    {{-- Contract Applicable + expandable contract details --}}
                    <div x-data="{ hasContract: {{ old('contract_applicable', $employee->contract_applicable) ? 'true' : 'false' }} }"
                         class="col-span-2 md:col-span-3 border border-slate-200 rounded-xl overflow-hidden">

                        {{-- Toggle header --}}
                        <div class="flex items-center gap-3 px-4 py-3 bg-slate-50 cursor-pointer"
                             @click="hasContract = !hasContract">
                            <input type="hidden" name="contract_applicable" value="0">
                            <input type="checkbox" name="contract_applicable" value="1" id="contract_applicable"
                                   class="w-4 h-4 text-blue-600"
                                   x-model="hasContract"
                                   @click.stop
                                   @change="hasContract = $event.target.checked"
                                   {{ old('contract_applicable', $employee->contract_applicable) ? 'checked' : '' }}>
                            <label for="contract_applicable" class="text-sm font-semibold text-slate-700 cursor-pointer select-none flex items-center gap-2">
                                <i class="fas fa-file-contract text-blue-500"></i>
                                Contract Applicable
                            </label>
                            <i class="fas fa-chevron-down text-slate-400 text-xs ml-auto transition-transform"
                               :class="hasContract ? 'rotate-180' : ''"></i>
                        </div>

                        {{-- Contract Details Panel --}}
                        <div x-show="hasContract" x-cloak class="p-4 border-t border-slate-200 space-y-4">

                            {{-- Dates row --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label">Contract Start Date</label>
                                    <input type="date" name="contract_start_date" class="form-input"
                                           value="{{ old('contract_start_date', $employee->contract_start_date?->format('Y-m-d')) }}">
                                </div>
                                <div>
                                    <label class="form-label">Contract End Date (Expiry) <span class="text-red-500">*</span></label>
                                    <input type="date" name="end_date" class="form-input"
                                           value="{{ old('end_date', $employee->end_date?->format('Y-m-d')) }}">
                                    <p class="text-xs text-slate-400 mt-1">The system will send alerts 30 days and 15 days before this date.</p>
                                </div>
                            </div>

                            {{-- Existing contract document --}}
                            @php $existingContract = $employee->documents->where('document_type','contract')->first(); @endphp
                            @if($existingContract)
                            <div class="flex items-center gap-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                <i class="fas fa-file-contract text-blue-500 text-lg"></i>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-blue-800 truncate">{{ $existingContract->file_name }}</p>
                                    <p class="text-xs text-blue-500">
                                        Uploaded {{ $existingContract->created_at?->format('d M Y') }}
                                        @if($existingContract->expiry_date) &bull; Expires {{ $existingContract->expiry_date->format('d M Y') }}@endif
                                    </p>
                                </div>
                                <a href="{{ Storage::url($existingContract->file_path) }}" target="_blank"
                                   class="btn-xs btn-blue shrink-0">
                                    <i class="fas fa-download mr-1"></i> View
                                </a>
                            </div>
                            <p class="text-xs text-slate-400">Uploading a new contract below will replace the existing one.</p>
                            @endif

                            {{-- File upload --}}
                            <div>
                                <label class="form-label">{{ $existingContract ? 'Replace Contract Document' : 'Upload Contract Document' }}</label>
                                <input type="file" name="contract_file" class="form-input text-sm"
                                       accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <p class="text-xs text-slate-400 mt-1">Accepted: PDF, Word, JPG, PNG — Max 10MB</p>
                            </div>

                            {{-- Notes --}}
                            <div>
                                <label class="form-label">Contract Notes</label>
                                <input type="text" name="contract_notes" class="form-input"
                                       placeholder="e.g. 6-month renewable, probation terms..."
                                       value="{{ old('contract_notes', $employee->contract_notes) }}">
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <input type="hidden" name="is_expatriate" value="0">
                        <input type="checkbox" name="is_expatriate" value="1" id="is_expatriate" class="w-4 h-4 text-blue-600"
                               @if(old('is_expatriate', $employee->is_expatriate)) checked @endif>
                        <label for="is_expatriate" class="text-sm text-slate-700 font-medium">Is Expatriate</label>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i> Save Changes</button>
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════
             TAB 2 — PLACEMENT
        ════════════════════════════════════════════════════════ --}}
        <div x-show="tab==='placement'" x-cloak class="space-y-5">
            <div class="card p-6">
                <h3 class="section-title"><i class="fas fa-sitemap text-teal-500"></i> Placement Details</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
                    <div>
                        <label class="form-label">Work Location</label>
                        <input type="text" name="work_location" class="form-input" placeholder="e.g. Head Office, Kampala"
                               value="{{ old('work_location', $employee->work_location) }}">
                    </div>
                    <div>
                        <label class="form-label">Department <span class="text-red-500">*</span></label>
                        <select name="department_id" class="form-input select2" required>
                            <option value="">— Select Department —</option>
                            @foreach($departments as $d)
                            <option value="{{ $d->id }}" @selected(old('department_id',$employee->department_id)==$d->id)>{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Sub Department</label>
                        <input type="text" name="sub_department" class="form-input"
                               value="{{ old('sub_department', $employee->sub_department) }}">
                    </div>
                    <div>
                        <label class="form-label">Designation <span class="text-red-500">*</span></label>
                        <select name="designation_id" class="form-input select2" required>
                            <option value="">— Select Designation —</option>
                            @foreach($designations as $d)
                            <option value="{{ $d->id }}" @selected(old('designation_id',$employee->designation_id)==$d->id)>{{ $d->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Employee Category</label>
                        <select name="employee_category" class="form-input">
                            <option value="">— Select —</option>
                            @foreach(['Management','Professional','Skilled','Semi-Skilled','Unskilled','Casual','Trainee'] as $c)
                            <option value="{{ $c }}" @selected(old('employee_category',$employee->employee_category)===$c)>{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Class</label>
                        <input type="text" name="class_name" class="form-input"
                               value="{{ old('class_name', $employee->class_name) }}">
                    </div>
                    <div>
                        <label class="form-label">Position Name</label>
                        <input type="text" name="position_name" class="form-input"
                               value="{{ old('position_name', $employee->position_name) }}">
                    </div>
                    <div>
                        <label class="form-label">Organisation Unit</label>
                        <input type="text" name="organization_unit" class="form-input"
                               value="{{ old('organization_unit', $employee->organization_unit) }}">
                    </div>
                    <div>
                        <label class="form-label">Reporting Manager</label>
                        <select name="manager_id" class="form-input select2">
                            <option value="">— No Manager —</option>
                            @foreach($managers as $m)
                            <option value="{{ $m->id }}" @selected(old('manager_id',$employee->manager_id)==$m->id)>{{ $m->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-3">
                <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i> Save Changes</button>
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════
             TAB 3 — PERSONAL DETAILS
        ════════════════════════════════════════════════════════ --}}
        <div x-show="tab==='personal'" x-cloak class="space-y-5">

            <div class="card p-6">
                <h3 class="section-title"><i class="fas fa-user-circle text-purple-500"></i> Personal Details</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
                    <div>
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-input">
                            <option value="">— Select —</option>
                            @foreach(['male'=>'Male','female'=>'Female','other'=>'Other'] as $v=>$l)
                            <option value="{{ $v }}" @selected(old('gender',$employee->gender)===$v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-input"
                               value="{{ old('date_of_birth', $employee->date_of_birth?->format('Y-m-d')) }}">
                    </div>
                    <div>
                        <label class="form-label">Anniversary Date</label>
                        <input type="date" name="anniversary_date" class="form-input"
                               value="{{ old('anniversary_date', $employee->anniversary_date?->format('Y-m-d')) }}">
                    </div>
                    <div>
                        <label class="form-label">Marital Status</label>
                        <select name="marital_status" class="form-input">
                            <option value="">— Select —</option>
                            @foreach(['single'=>'Single','married'=>'Married','divorced'=>'Divorced','widowed'=>'Widowed','separated'=>'Separated'] as $v=>$l)
                            <option value="{{ $v }}" @selected(old('marital_status',$employee->marital_status)===$v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Children</label>
                        <input type="number" name="children_count" class="form-input" min="0"
                               value="{{ old('children_count', $employee->children_count ?? 0) }}">
                    </div>
                    <div>
                        <label class="form-label">Dependents</label>
                        <input type="number" name="dependents_count" class="form-input" min="0" step="0.5"
                               value="{{ old('dependents_count', $employee->dependents_count ?? 0) }}">
                    </div>
                    <div>
                        <label class="form-label">Religion</label>
                        <input type="text" name="religion" class="form-input"
                               value="{{ old('religion', $employee->religion) }}">
                    </div>
                    <div>
                        <label class="form-label">Nationality</label>
                        <input type="text" name="nationality" class="form-input" placeholder="e.g. Ugandan"
                               value="{{ old('nationality', $employee->nationality) }}">
                    </div>
                    <div>
                        <label class="form-label">Mother Tongue</label>
                        <input type="text" name="mother_tongue" class="form-input"
                               value="{{ old('mother_tongue', $employee->mother_tongue) }}">
                    </div>
                    <div>
                        <label class="form-label">National ID</label>
                        <input type="text" name="national_id" class="form-input"
                               value="{{ old('national_id', $employee->national_id) }}">
                    </div>
                    <div>
                        <label class="form-label">Passport Number</label>
                        <input type="text" name="passport_number" class="form-input"
                               value="{{ old('passport_number', $employee->passport_number) }}">
                    </div>
                    <div>
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-input"
                               value="{{ old('phone', $employee->phone) }}">
                    </div>
                    <div>
                        <label class="form-label">Personal Email</label>
                        <input type="email" name="personal_email" class="form-input"
                               value="{{ old('personal_email', $employee->personal_email) }}">
                    </div>
                    <div>
                        <label class="form-label">City</label>
                        <input type="text" name="city" class="form-input"
                               value="{{ old('city', $employee->city) }}">
                    </div>
                    <div>
                        <label class="form-label">Country</label>
                        <input type="text" name="country" class="form-input" placeholder="e.g. Uganda"
                               value="{{ old('country', $employee->country ?? 'Uganda') }}">
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" rows="2" class="form-input">{{ old('address', $employee->address) }}</textarea>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label class="form-label">Bio / Notes</label>
                        <textarea name="bio" rows="3" class="form-input" placeholder="Any additional notes about this employee...">{{ old('bio', $employee->bio) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="card p-6">
                <h3 class="section-title"><i class="fas fa-phone-alt text-green-500"></i> Emergency & Next of Kin</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="form-label">Emergency Contact Name</label>
                        <input type="text" name="emergency_contact_name" class="form-input"
                               value="{{ old('emergency_contact_name', $employee->emergency_contact_name) }}">
                    </div>
                    <div>
                        <label class="form-label">Emergency Contact Phone</label>
                        <input type="text" name="emergency_contact_phone" class="form-input"
                               value="{{ old('emergency_contact_phone', $employee->emergency_contact_phone) }}">
                    </div>
                    <div>
                        <label class="form-label">Next of Kin Name</label>
                        <input type="text" name="next_of_kin_name" class="form-input"
                               value="{{ old('next_of_kin_name', $employee->next_of_kin_name) }}">
                    </div>
                    <div>
                        <label class="form-label">Relationship</label>
                        <input type="text" name="next_of_kin_relation" class="form-input" placeholder="e.g. Spouse, Parent"
                               value="{{ old('next_of_kin_relation', $employee->next_of_kin_relation) }}">
                    </div>
                    <div>
                        <label class="form-label">Next of Kin Phone</label>
                        <input type="text" name="next_of_kin_phone" class="form-input"
                               value="{{ old('next_of_kin_phone', $employee->next_of_kin_phone) }}">
                    </div>
                    <div>
                        <label class="form-label">Next of Kin Email</label>
                        <input type="email" name="next_of_kin_email" class="form-input"
                               value="{{ old('next_of_kin_email', $employee->next_of_kin_email) }}">
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i> Save Changes</button>
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════
             TAB 4 — STATUTORY
        ════════════════════════════════════════════════════════ --}}
        <div x-show="tab==='statutory'" x-cloak class="space-y-5">

            {{-- Insurance --}}
            <div class="card p-5">
                <h3 class="section-title"><i class="fas fa-shield-alt text-blue-500"></i> Insurance Details</h3>
                <div class="mt-3 flex items-center gap-3">
                    <input type="hidden" name="insurance_relief" value="0">
                    <input type="checkbox" name="insurance_relief" value="1" id="insurance_relief" class="w-4 h-4 text-blue-600"
                           @if(old('insurance_relief', $employee->insurance_relief)) checked @endif>
                    <label for="insurance_relief" class="text-sm text-slate-700 font-medium">Insurance Relief Applicable</label>
                </div>
            </div>

            {{-- Statutory IDs --}}
            <div class="card p-6">
                <h3 class="section-title"><i class="fas fa-id-badge text-orange-500"></i> Statutory Details</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                    <div>
                        <label class="form-label">NSSF Number</label>
                        <input type="text" name="nssf_number" class="form-input"
                               value="{{ old('nssf_number', $employee->nssf_number) }}">
                    </div>
                    <div>
                        <label class="form-label">TIN Number</label>
                        <input type="text" name="tin_number" class="form-input"
                               value="{{ old('tin_number', $employee->tin_number) }}">
                    </div>
                    <div>
                        <label class="form-label">IFMS Supplier No.</label>
                        <input type="text" name="ifms_supplier_no" class="form-input"
                               value="{{ old('ifms_supplier_no', $employee->ifms_supplier_no) }}">
                    </div>
                    <div>
                        <label class="form-label">Pension Number</label>
                        <input type="text" name="pension_no" class="form-input"
                               value="{{ old('pension_no', $employee->pension_no) }}">
                    </div>
                </div>
            </div>

            {{-- Statutory Deductions --}}
            <div class="card p-6">
                <h3 class="section-title"><i class="fas fa-calculator text-red-500"></i> Statutory Deductions</h3>
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
                    @php
                    $bools = [
                        ['charge_nssf','Charge NSSF'],
                        ['force_fixed_nssf','Force Fixed NSSF'],
                        ['nssf_paid_by_employer','NSSF Paid by Employer'],
                        ['do_not_charge_nssf_employee','Do Not Charge NSSF (Employee)'],
                        ['charge_lst','Charge LST'],
                        ['lst_paid_by_employer','LST Paid by Employer'],
                        ['tax_paid_by_employer','Tax Paid by Employer'],
                    ];
                    @endphp
                    @foreach($bools as [$field, $label])
                    <div class="flex items-center gap-3">
                        <input type="hidden" name="{{ $field }}" value="0">
                        <input type="checkbox" name="{{ $field }}" value="1" id="{{ $field }}" class="w-4 h-4 text-blue-600"
                               @if(old($field, $employee->$field)) checked @endif>
                        <label for="{{ $field }}" class="text-sm text-slate-700">{{ $label }}</label>
                    </div>
                    @endforeach

                    <div class="sm:col-span-2 lg:col-span-3 grid grid-cols-2 gap-4 mt-2">
                        <div>
                            <label class="form-label">Fixed NSSF Amount</label>
                            <input type="number" name="fixed_nssf_amount" class="form-input" step="0.01" placeholder="0.00"
                                   value="{{ old('fixed_nssf_amount', $employee->fixed_nssf_amount) }}">
                        </div>
                        <div>
                            <label class="form-label">Voluntary NSSF Amount</label>
                            <input type="number" name="voluntary_nssf" class="form-input" step="0.01" placeholder="0.00"
                                   value="{{ old('voluntary_nssf', $employee->voluntary_nssf) }}">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Special Tax --}}
            <div class="card p-5">
                <h3 class="section-title"><i class="fas fa-percent text-yellow-500"></i> Miscellaneous Tax</h3>
                <div class="mt-3 flex flex-wrap items-center gap-6">
                    <div class="flex items-center gap-3">
                        <input type="hidden" name="apply_special_tax" value="0">
                        <input type="checkbox" name="apply_special_tax" value="1" id="apply_special_tax" class="w-4 h-4 text-blue-600"
                               @if(old('apply_special_tax', $employee->apply_special_tax)) checked @endif>
                        <label for="apply_special_tax" class="text-sm text-slate-700 font-medium">Apply Special Tax</label>
                    </div>
                    <div class="flex items-center gap-3">
                        <label class="form-label mb-0">Special Tax %</label>
                        <input type="number" name="special_tax_percentage" class="form-input w-28" step="0.01" min="0" max="100"
                               value="{{ old('special_tax_percentage', $employee->special_tax_percentage) }}">
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i> Save Changes</button>
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════
             TAB 5 — SALARY & CONTRIBUTIONS
        ════════════════════════════════════════════════════════ --}}
        <div x-show="tab==='salary'" x-cloak class="space-y-5">

            {{-- Banking --}}
            <div class="card p-6">
                <h3 class="section-title"><i class="fas fa-university text-green-500"></i> Banking Details</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                    <div>
                        <label class="form-label">Payment Mode</label>
                        <select name="payment_mode" class="form-input">
                            @foreach(['bank'=>'Bank','cash'=>'Cash','cheque'=>'Cheque','mobile_money'=>'Mobile Money'] as $v=>$l)
                            <option value="{{ $v }}" @selected(old('payment_mode',$employee->payment_mode ?? 'bank')===$v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Bank Name</label>
                        <input type="text" name="bank_name" class="form-input"
                               value="{{ old('bank_name', $employee->bank_name) }}">
                    </div>
                    <div>
                        <label class="form-label">Account Number</label>
                        <input type="text" name="bank_account" class="form-input"
                               value="{{ old('bank_account', $employee->bank_account) }}">
                    </div>
                    <div>
                        <label class="form-label">Branch</label>
                        <input type="text" name="bank_branch" class="form-input"
                               value="{{ old('bank_branch', $employee->bank_branch) }}">
                    </div>
                    <div>
                        <label class="form-label">Tax Number (PAYE)</label>
                        <input type="text" name="tax_number" class="form-input"
                               value="{{ old('tax_number', $employee->tax_number) }}">
                    </div>
                    <div>
                        <label class="form-label">Salary Grade</label>
                        <select name="salary_grade" class="form-input">
                            <option value="">— None —</option>
                            @foreach($grades as $g)
                            <option value="{{ $g->grade }}" @selected(old('salary_grade',$employee->salary_grade)===$g->grade)>{{ $g->grade }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Calculation Hours --}}
            <div class="card p-6">
                <h3 class="section-title"><i class="fas fa-clock text-blue-500"></i> Salary Calculation Hours</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mt-4">
                    @foreach([
                        ['ot_calc_hours','OT Hours'],
                        ['absenteeism_calc_hours','Absenteeism Hours'],
                        ['ot1_calc_hours','OT1 Hours'],
                        ['ot2_calc_hours','OT2 Hours'],
                        ['min_daily_working_hours','Min Daily Hours'],
                    ] as [$f,$l])
                    <div>
                        <label class="form-label">{{ $l }}</label>
                        <input type="number" name="{{ $f }}" class="form-input" step="0.01" placeholder="0.00"
                               value="{{ old($f, $employee->$f) }}">
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Provident Fund --}}
            <div class="card p-6">
                <h3 class="section-title"><i class="fas fa-piggy-bank text-pink-500"></i> Provident Fund</h3>
                <div class="mt-3 mb-4 flex items-center gap-3">
                    <input type="hidden" name="pf_applicable" value="0">
                    <input type="checkbox" name="pf_applicable" value="1" id="pf_applicable" class="w-4 h-4 text-blue-600"
                           @if(old('pf_applicable', $employee->pf_applicable)) checked @endif>
                    <label for="pf_applicable" class="text-sm font-medium text-slate-700">Provident Fund Applicable</label>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="form-label">Deduction Type</label>
                        <select name="pf_deduction_type" class="form-input">
                            <option value="">— Select —</option>
                            <option value="fixed"      @selected(old('pf_deduction_type',$employee->pf_deduction_type)==='fixed')>Fixed Amount</option>
                            <option value="percentage" @selected(old('pf_deduction_type',$employee->pf_deduction_type)==='percentage')>Percentage</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Calculate On</label>
                        <select name="pf_calculate_on" class="form-input">
                            <option value="">— Select —</option>
                            @foreach(['gross'=>'Gross','basic'=>'Basic','net'=>'Net'] as $v=>$l)
                            <option value="{{ $v }}" @selected(old('pf_calculate_on',$employee->pf_calculate_on)===$v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">PF Scheme</label>
                        <input type="text" name="pf_scheme" class="form-input"
                               value="{{ old('pf_scheme', $employee->pf_scheme) }}">
                    </div>
                    <div>
                        <label class="form-label">Employee Rate / Amount</label>
                        <input type="number" name="pf_employee_rate" class="form-input" step="0.0001"
                               value="{{ old('pf_employee_rate', $employee->pf_employee_rate) }}">
                    </div>
                    <div>
                        <label class="form-label">Employer Rate / Amount</label>
                        <input type="number" name="pf_employer_rate" class="form-input" step="0.0001"
                               value="{{ old('pf_employer_rate', $employee->pf_employer_rate) }}">
                    </div>
                    <div>
                        <label class="form-label">Voluntary PF Amount</label>
                        <input type="number" name="voluntary_pf_amount" class="form-input" step="0.01"
                               value="{{ old('voluntary_pf_amount', $employee->voluntary_pf_amount) }}">
                    </div>
                    <div>
                        <label class="form-label">Vol. PF Deduction Type</label>
                        <select name="voluntary_pf_deduction_type" class="form-input">
                            <option value="">— Select —</option>
                            <option value="fixed"      @selected(old('voluntary_pf_deduction_type',$employee->voluntary_pf_deduction_type)==='fixed')>Fixed</option>
                            <option value="percentage" @selected(old('voluntary_pf_deduction_type',$employee->voluntary_pf_deduction_type)==='percentage')>Percentage</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Vol. PF Calculate On</label>
                        <select name="voluntary_pf_calculate_on" class="form-input">
                            <option value="">— Select —</option>
                            @foreach(['gross'=>'Gross','basic'=>'Basic'] as $v=>$l)
                            <option value="{{ $v }}" @selected(old('voluntary_pf_calculate_on',$employee->voluntary_pf_calculate_on)===$v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-center gap-3 pt-5">
                        <input type="hidden" name="do_not_deduct_voluntary_pf" value="0">
                        <input type="checkbox" name="do_not_deduct_voluntary_pf" value="1" id="do_not_deduct_voluntary_pf" class="w-4 h-4"
                               @if(old('do_not_deduct_voluntary_pf', $employee->do_not_deduct_voluntary_pf)) checked @endif>
                        <label for="do_not_deduct_voluntary_pf" class="text-sm text-slate-700">Do Not Deduct Vol. PF</label>
                    </div>
                </div>
            </div>

            {{-- Pension --}}
            <div class="card p-6">
                <h3 class="section-title"><i class="fas fa-hand-holding-usd text-yellow-600"></i> Pension</h3>
                <div class="mt-3 mb-4 flex items-center gap-3">
                    <input type="hidden" name="pension_applicable" value="0">
                    <input type="checkbox" name="pension_applicable" value="1" id="pension_applicable" class="w-4 h-4 text-blue-600"
                           @if(old('pension_applicable', $employee->pension_applicable)) checked @endif>
                    <label for="pension_applicable" class="text-sm font-medium text-slate-700">Pension Applicable</label>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="form-label">Deduction Type</label>
                        <select name="pension_deduction_type" class="form-input">
                            <option value="">— Select —</option>
                            <option value="fixed"      @selected(old('pension_deduction_type',$employee->pension_deduction_type)==='fixed')>Fixed Amount</option>
                            <option value="percentage" @selected(old('pension_deduction_type',$employee->pension_deduction_type)==='percentage')>Percentage</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Calculate On</label>
                        <select name="pension_calculate_on" class="form-input">
                            <option value="">— Select —</option>
                            @foreach(['gross'=>'Gross','basic'=>'Basic','net'=>'Net'] as $v=>$l)
                            <option value="{{ $v }}" @selected(old('pension_calculate_on',$employee->pension_calculate_on)===$v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Pension Scheme</label>
                        <input type="text" name="pension_scheme" class="form-input"
                               value="{{ old('pension_scheme', $employee->pension_scheme) }}">
                    </div>
                    <div>
                        <label class="form-label">Employee Rate / Amount</label>
                        <input type="number" name="pension_employee_rate" class="form-input" step="0.0001"
                               value="{{ old('pension_employee_rate', $employee->pension_employee_rate) }}">
                    </div>
                    <div>
                        <label class="form-label">Employer Rate / Amount</label>
                        <input type="number" name="pension_employer_rate" class="form-input" step="0.0001"
                               value="{{ old('pension_employer_rate', $employee->pension_employer_rate) }}">
                    </div>
                    <div>
                        <label class="form-label">Voluntary Pension Amount</label>
                        <input type="number" name="voluntary_pension_amount" class="form-input" step="0.01"
                               value="{{ old('voluntary_pension_amount', $employee->voluntary_pension_amount) }}">
                    </div>
                    <div>
                        <label class="form-label">Vol. Pension Deduction Type</label>
                        <select name="voluntary_pension_deduction_type" class="form-input">
                            <option value="">— Select —</option>
                            <option value="fixed"      @selected(old('voluntary_pension_deduction_type',$employee->voluntary_pension_deduction_type)==='fixed')>Fixed</option>
                            <option value="percentage" @selected(old('voluntary_pension_deduction_type',$employee->voluntary_pension_deduction_type)==='percentage')>Percentage</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Vol. Pension Calculate On</label>
                        <select name="voluntary_pension_calculate_on" class="form-input">
                            <option value="">— Select —</option>
                            @foreach(['gross'=>'Gross','basic'=>'Basic'] as $v=>$l)
                            <option value="{{ $v }}" @selected(old('voluntary_pension_calculate_on',$employee->voluntary_pension_calculate_on)===$v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-center gap-3 pt-5">
                        <input type="hidden" name="do_not_deduct_voluntary_pension" value="0">
                        <input type="checkbox" name="do_not_deduct_voluntary_pension" value="1" id="do_not_deduct_voluntary_pension" class="w-4 h-4"
                               @if(old('do_not_deduct_voluntary_pension', $employee->do_not_deduct_voluntary_pension)) checked @endif>
                        <label for="do_not_deduct_voluntary_pension" class="text-sm text-slate-700">Do Not Deduct Vol. Pension</label>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i> Save Changes</button>
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════
             TAB 6 — STATUS & FLAGS
        ════════════════════════════════════════════════════════ --}}
        <div x-show="tab==='status'" x-cloak class="space-y-5">

            {{-- Employment Status --}}
            <div class="card p-6">
                <h3 class="section-title"><i class="fas fa-toggle-on text-blue-500"></i> Employment Status</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
                    <div>
                        <label class="form-label">Status</label>
                        <select name="status" class="form-input">
                            @foreach(['active'=>'Active','on_leave'=>'On Leave','suspended'=>'Suspended','terminated'=>'Terminated'] as $v=>$l)
                            <option value="{{ $v }}" @selected(old('status',$employee->status)===$v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Hire Date</label>
                        <input type="date" name="hire_date" class="form-input"
                               value="{{ old('hire_date', $employee->hire_date?->format('Y-m-d')) }}">
                    </div>
                    <div>
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-input"
                               value="{{ old('end_date', $employee->end_date?->format('Y-m-d')) }}">
                    </div>
                </div>
            </div>

            {{-- Blacklist --}}
            <div class="card p-6">
                <h3 class="section-title"><i class="fas fa-ban text-red-500"></i> Blacklist Information</h3>
                <div class="mt-3 mb-4 flex items-center gap-3">
                    <input type="hidden" name="is_blacklisted" value="0">
                    <input type="checkbox" name="is_blacklisted" value="1" id="is_blacklisted" class="w-4 h-4 text-red-600"
                           @if(old('is_blacklisted', $employee->is_blacklisted)) checked @endif>
                    <label for="is_blacklisted" class="text-sm font-semibold text-red-700">Mark as Blacklisted</label>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Blacklist Date</label>
                        <input type="date" name="blacklist_date" class="form-input"
                               value="{{ old('blacklist_date', $employee->blacklist_date?->format('Y-m-d')) }}">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="form-label">Blacklist Reason</label>
                        <textarea name="blacklist_reason" rows="3" class="form-input"
                                  placeholder="Reason for blacklisting...">{{ old('blacklist_reason', $employee->blacklist_reason) }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Hold --}}
            <div class="card p-6">
                <h3 class="section-title"><i class="fas fa-pause-circle text-yellow-500"></i> Hold Information</h3>
                <div class="mt-3 mb-4 flex items-center gap-3">
                    <input type="hidden" name="on_hold" value="0">
                    <input type="checkbox" name="on_hold" value="1" id="on_hold" class="w-4 h-4 text-yellow-600"
                           @if(old('on_hold', $employee->on_hold)) checked @endif>
                    <label for="on_hold" class="text-sm font-semibold text-yellow-700">Place Employee On Hold</label>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Hold Start Date</label>
                        <input type="date" name="hold_date" class="form-input"
                               value="{{ old('hold_date', $employee->hold_date?->format('Y-m-d')) }}">
                    </div>
                    <div>
                        <label class="form-label">Hold End Date <span class="text-slate-400 font-normal">(Expected Return)</span></label>
                        <input type="date" name="hold_end_date" class="form-input"
                               value="{{ old('hold_end_date', $employee->hold_end_date?->format('Y-m-d')) }}">
                        <p class="text-xs text-slate-400 mt-1"><i class="fas fa-info-circle mr-1"></i>System will auto-release hold when this date passes</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="form-label">Hold Reason</label>
                        <textarea name="hold_reason" rows="3" class="form-input"
                                  placeholder="Reason for placing on hold...">{{ old('hold_reason', $employee->hold_reason) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('employees.show', $employee) }}" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i> Save Changes</button>
            </div>
        </div>

    </form>
</div>

@push('styles')
<style>
.section-title { @apply text-sm font-semibold text-slate-600 uppercase tracking-wider flex items-center gap-2; }
[x-cloak] { display: none !important; }
</style>
@endpush

@endsection
