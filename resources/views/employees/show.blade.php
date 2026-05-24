@extends("layouts.app")
@section("title", $employee->full_name . " — Employee Central")
@section("breadcrumb")
<a href="{{ route('employees.index') }}" class="text-slate-500 hover:text-slate-700 text-sm">Employee Central</a>
<i class="fas fa-chevron-right text-slate-300 text-xs mx-1"></i>
<span class="text-slate-600 text-sm font-medium">{{ $employee->full_name }}</span>
@endsection
@section("content")

@php
    $canEdit = auth()->user()->hasAnyRole(['super-admin','hr-admin']);
@endphp

<div x-data="{ tab: 'basic' }" class="space-y-5">

    {{-- Top Header Bar --}}
    <div class="card p-4 flex flex-col md:flex-row items-start md:items-center gap-4">
        <img src="{{ $employee->avatar_url }}" class="w-16 h-16 rounded-2xl object-cover border-4 border-blue-100 shrink-0">
        <div class="flex-1 min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold text-slate-900">{{ $employee->full_name }}</h1>
                {!! $employee->status_badge !!}
                @if($employee->is_blacklisted)<span class="badge-red">Blacklisted</span>@endif
                @if($employee->on_hold)<span class="badge-yellow">On Hold</span>@endif
                @if($employee->is_on_probation)<span class="badge-orange">On Probation</span>@endif
            </div>
            <p class="text-sm text-slate-500 mt-0.5">{{ $employee->designation?->title }} &bull; {{ $employee->department?->name }}</p>
            <div class="flex flex-wrap gap-4 mt-1.5 text-xs text-slate-400">
                <span><i class="fas fa-id-badge mr-1"></i> {{ $employee->emp_number }}</span>
                @if($employee->payroll_number)<span><i class="fas fa-hashtag mr-1"></i> Payroll: {{ $employee->payroll_number }}</span>@endif
                <span><i class="fas fa-envelope mr-1"></i> {{ $employee->user?->email }}</span>
                @if($employee->phone)<span><i class="fas fa-phone mr-1"></i> {{ $employee->phone }}</span>@endif
                <span><i class="fas fa-calendar mr-1"></i> Hired {{ $employee->hire_date?->format('d M Y') }}</span>
            </div>
        </div>
        <div class="flex gap-2 shrink-0">
            @if($canEdit)
            <a href="{{ route('employees.edit', $employee) }}" class="btn-primary text-xs"><i class="fas fa-pen"></i> Edit Profile</a>
            @endif
            <a href="{{ route('employees.documents', $employee) }}" class="btn-secondary text-xs"><i class="fas fa-folder"></i> Documents</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-5">

        {{-- Left Sidebar --}}
        <div class="space-y-4">
            {{-- Quick Info --}}
            <div class="card p-4 space-y-3">
                <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Quick Info</h3>
                @php $assignedClient = $employee->clients->first(); @endphp
                @if($assignedClient)
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                    <p class="text-xs font-semibold text-blue-500 uppercase mb-0.5"><i class="fas fa-building mr-1"></i> Assigned Company</p>
                    <p class="text-sm font-bold text-blue-900">{{ $assignedClient->company_name }}</p>
                </div>
                @endif
                <div class="space-y-2">
                    @if($employee->work_location)<div class="flex gap-2 text-xs text-slate-600"><i class="fas fa-map-marker-alt w-4 text-slate-400 mt-0.5"></i><span>{{ $employee->work_location }}</span></div>@endif
                    @if($employee->employment_type)<div class="flex gap-2 text-xs text-slate-600"><i class="fas fa-briefcase w-4 text-slate-400"></i><span>{{ ucfirst(str_replace('_',' ',$employee->employment_type)) }}</span></div>@endif
                    @if($employee->national_id)<div class="flex gap-2 text-xs text-slate-600"><i class="fas fa-id-card w-4 text-slate-400"></i><span>{{ $employee->national_id }}</span></div>@endif
                    @if($employee->gender)<div class="flex gap-2 text-xs text-slate-600"><i class="fas fa-venus-mars w-4 text-slate-400"></i><span>{{ ucfirst($employee->gender) }}</span></div>@endif
                    @if($employee->date_of_birth)<div class="flex gap-2 text-xs text-slate-600"><i class="fas fa-birthday-cake w-4 text-slate-400"></i><span>{{ $employee->date_of_birth->format('d M Y') }}</span></div>@endif
                </div>
            </div>

            {{-- Probation --}}
            @if($employee->probation_status)
            <div class="card p-4">
                <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Probation</h3>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-slate-700">Status</span>
                    {!! $employee->probation_status_badge !!}
                </div>
                @if($employee->probation_end_date)
                <div class="mt-2 text-xs text-slate-500">Ends: {{ $employee->probation_end_date->format('d M Y') }}</div>
                @if($employee->probation_days_left !== null && $employee->probation_days_left > 0)
                <div class="mt-1 text-xs font-semibold text-orange-600">{{ $employee->probation_days_left }} days left</div>
                @endif
                @endif
            </div>
            @endif

            {{-- Nav Tabs --}}
            <div class="card p-2 space-y-0.5">
                @foreach([
                    'basic'      => ['fa-user','Basic Info'],
                    'placement'  => ['fa-briefcase','Placement'],
                    'personal'   => ['fa-heart','Personal Details'],
                    'statutory'  => ['fa-landmark','Statutory & Tax'],
                    'salary'     => ['fa-coins','Salary & Funds'],
                    'flags'      => ['fa-flag','Status & Flags'],
                    'leave'      => ['fa-umbrella-beach','Leave Balances'],
                    'history'    => ['fa-history','Employment History'],
                ] as $key => [$icon, $label])
                <button @click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}' ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-50'"
                    class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm font-medium transition-all">
                    <i class="fas {{ $icon }} w-4 text-center shrink-0"></i><span>{{ $label }}</span>
                </button>
                @endforeach
            </div>
        </div>

        {{-- Main Content --}}
        <div class="lg:col-span-3 space-y-0">

            {{-- BASIC INFO --}}
            <div x-show="tab === 'basic'" class="space-y-4">
                {{-- Identity --}}
                <div class="card p-5">
                    <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2"><i class="fas fa-id-card text-blue-500"></i> Identity</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        @php $fields = [
                            'Title'         => $employee->title ?? '—',
                            'First Name'    => $employee->first_name ?? '—',
                            'Middle Name'   => $employee->middle_name ?? '—',
                            'Last Name'     => $employee->last_name ?? '—',
                            'Employee No.'  => $employee->emp_number ?? '—',
                            'Payroll No.'   => $employee->payroll_number ?? '—',
                        ]; @endphp
                        @foreach($fields as $lbl => $val)
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">{{ $lbl }}</p>
                            <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ $val }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Job Details --}}
                <div class="card p-5">
                    <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2"><i class="fas fa-briefcase text-blue-500"></i> Job Details</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        @php $fields = [
                            'Department'       => $employee->department?->name ?? '—',
                            'Designation'      => $employee->designation?->title ?? '—',
                            'Manager'          => $employee->manager?->full_name ?? '—',
                            'Employment Type'  => ucfirst(str_replace('_',' ',$employee->employment_type ?? '—')),
                            'Salary Grade'     => $employee->salary_grade ?? '—',
                            'Hire Date'        => $employee->hire_date?->format('d M Y') ?? '—',
                            'End Date'         => $employee->end_date?->format('d M Y') ?? '—',
                            'Status'           => ucfirst($employee->status ?? '—'),
                        ]; @endphp
                        @foreach($fields as $lbl => $val)
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">{{ $lbl }}</p>
                            <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ $val }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Job Profile --}}
                <div class="card p-5">
                    <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2"><i class="fas fa-calendar-alt text-blue-500"></i> Job Profile</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        @php $fields = [
                            'Week Off Type'    => ucfirst(str_replace('_',' ',$employee->week_off_type ?? '—')),
                            'Week Off Day'     => $employee->week_off_day ?? '—',
                            'Holiday Calendar' => $employee->holiday_calendar ?? '—',
                            'Contract Applicable' => $employee->contract_applicable ? 'Yes' : 'No',
                            'Expatriate'       => $employee->is_expatriate ? 'Yes' : 'No',
                        ]; @endphp
                        @foreach($fields as $lbl => $val)
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">{{ $lbl }}</p>
                            <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ $val }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- PLACEMENT --}}
            <div x-show="tab === 'placement'" class="card p-5">
                <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2"><i class="fas fa-map-marker-alt text-blue-500"></i> Placement & Deployment</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    @php $fields = [
                        'Work Location'    => $employee->work_location ?? '—',
                        'Sub Department'   => $employee->sub_department ?? '—',
                        'Employee Category'=> $employee->employee_category ?? '—',
                        'Class'            => $employee->class_name ?? '—',
                        'Position Name'    => $employee->position_name ?? '—',
                        'Organization Unit'=> $employee->organization_unit ?? '—',
                    ]; @endphp
                    @foreach($fields as $lbl => $val)
                    <div>
                        <p class="text-xs text-slate-400 uppercase tracking-wider">{{ $lbl }}</p>
                        <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ $val }}</p>
                    </div>
                    @endforeach
                </div>

                {{-- Client Assignment History --}}
                <div class="mt-6 border-t border-slate-100 pt-4">
                    <h4 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2"><i class="fas fa-history text-slate-400"></i> Client Placement History</h4>
                    @if($employee->clientTransfers->count())
                    <div class="space-y-2">
                        @foreach($employee->clientTransfers as $t)
                        <div class="flex items-center justify-between bg-slate-50 rounded-lg px-4 py-2.5">
                            <div>
                                <p class="text-sm font-semibold text-slate-800">{{ $t->client?->company_name ?? '—' }}</p>
                                <p class="text-xs text-slate-400">
                                    {{ $t->effective_date?->format('d M Y') }}
                                    @if($t->end_date) — {{ $t->end_date->format('d M Y') }}@endif
                                    @if($t->reason) &bull; {{ $t->reason }}@endif
                                </p>
                            </div>
                            <span class="badge-{{ $t->type === 'assignment' ? 'green' : ($t->type === 'transfer' ? 'blue' : 'red') }}">{{ ucfirst($t->type) }}</span>
                        </div>
                        @endforeach
                    </div>
                    @elseif($employee->clients->count())
                    <div class="space-y-2">
                        @foreach($employee->clients as $client)
                        <div class="flex items-center justify-between bg-slate-50 rounded-lg px-4 py-2.5">
                            <div>
                                <p class="text-sm font-semibold text-slate-800">{{ $client->company_name }}</p>
                                @if($client->pivot->created_at)<p class="text-xs text-slate-400">Assigned {{ \Carbon\Carbon::parse($client->pivot->created_at)->format('d M Y') }}</p>@endif
                            </div>
                            <span class="badge-blue text-xs">Current</span>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p class="text-sm text-slate-400">No client placement history recorded.</p>
                    @endif
                </div>
            </div>

            {{-- PERSONAL DETAILS --}}
            <div x-show="tab === 'personal'" class="space-y-4">
                <div class="card p-5">
                    <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2"><i class="fas fa-user text-blue-500"></i> Personal Information</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        @php $fields = [
                            'Date of Birth'    => $employee->date_of_birth?->format('d M Y') ?? '—',
                            'Gender'           => ucfirst($employee->gender ?? '—'),
                            'Marital Status'   => ucfirst(str_replace('_',' ',$employee->marital_status ?? '—')),
                            'Children'         => $employee->children_count ?? '—',
                            'Dependents'       => $employee->dependents_count ?? '—',
                            'Anniversary'      => $employee->anniversary_date?->format('d M Y') ?? '—',
                            'Nationality'      => $employee->nationality ?? '—',
                            'Religion'         => $employee->religion ?? '—',
                            'Mother Tongue'    => $employee->mother_tongue ?? '—',
                            'National ID'      => $employee->national_id ?? '—',
                            'Passport No.'     => $employee->passport_number ?? '—',
                            'Personal Email'   => $employee->personal_email ?? '—',
                            'Phone'            => $employee->phone ?? '—',
                        ]; @endphp
                        @foreach($fields as $lbl => $val)
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">{{ $lbl }}</p>
                            <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ $val }}</p>
                        </div>
                        @endforeach
                        @if($employee->address)
                        <div class="col-span-2 md:col-span-3">
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Address</p>
                            <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ $employee->address }}{{ $employee->city ? ', '.$employee->city : '' }}{{ $employee->country ? ', '.$employee->country : '' }}</p>
                        </div>
                        @endif
                        @if($employee->bio)
                        <div class="col-span-2 md:col-span-3">
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Bio</p>
                            <p class="text-sm text-slate-700 mt-0.5 leading-relaxed">{{ $employee->bio }}</p>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Emergency Contact --}}
                <div class="card p-5">
                    <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2"><i class="fas fa-phone-alt text-red-500"></i> Emergency Contact</h3>
                    <div class="grid grid-cols-2 gap-4">
                        @php $fields = [
                            'Name'  => $employee->emergency_contact_name ?? '—',
                            'Phone' => $employee->emergency_contact_phone ?? '—',
                        ]; @endphp
                        @foreach($fields as $lbl => $val)
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">{{ $lbl }}</p>
                            <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ $val }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Next of Kin --}}
                <div class="card p-5">
                    <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2"><i class="fas fa-users text-green-500"></i> Next of Kin</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        @php $fields = [
                            'Name'     => $employee->next_of_kin_name ?? '—',
                            'Relation' => $employee->next_of_kin_relation ?? '—',
                            'Phone'    => $employee->next_of_kin_phone ?? '—',
                            'Email'    => $employee->next_of_kin_email ?? '—',
                        ]; @endphp
                        @foreach($fields as $lbl => $val)
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">{{ $lbl }}</p>
                            <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ $val }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- STATUTORY & TAX --}}
            <div x-show="tab === 'statutory'" class="space-y-4">
                {{-- IDs --}}
                <div class="card p-5">
                    <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2"><i class="fas fa-file-invoice text-blue-500"></i> Statutory IDs</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        @php $fields = [
                            'NSSF Number'        => $employee->nssf_number ?? '—',
                            'TIN Number'         => $employee->tin_number ?? '—',
                            'IFMS Supplier No.'  => $employee->ifms_supplier_no ?? '—',
                            'Pension No.'        => $employee->pension_no ?? '—',
                            'Tax Number'         => $employee->tax_number ?? '—',
                        ]; @endphp
                        @foreach($fields as $lbl => $val)
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">{{ $lbl }}</p>
                            <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ $val }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- NSSF Settings --}}
                <div class="card p-5">
                    <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2"><i class="fas fa-shield-alt text-green-500"></i> NSSF Settings</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        @php $bools = [
                            'Charge NSSF'              => $employee->charge_nssf,
                            'Force Fixed NSSF'         => $employee->force_fixed_nssf,
                            'NSSF Paid by Employer'    => $employee->nssf_paid_by_employer,
                            'Do Not Charge NSSF (Emp)' => $employee->do_not_charge_nssf_employee,
                            'Insurance Relief'         => $employee->insurance_relief,
                        ]; @endphp
                        @foreach($bools as $lbl => $val)
                        <div class="flex items-center gap-2">
                            <span class="{{ $val ? 'text-green-500' : 'text-slate-300' }}"><i class="fas {{ $val ? 'fa-check-circle' : 'fa-times-circle' }}"></i></span>
                            <span class="text-sm text-slate-700">{{ $lbl }}</span>
                        </div>
                        @endforeach
                        @if($employee->voluntary_nssf)
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Voluntary NSSF</p>
                            <p class="text-sm font-semibold text-slate-800">{{ number_format($employee->voluntary_nssf, 2) }}</p>
                        </div>
                        @endif
                        @if($employee->fixed_nssf_amount)
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Fixed NSSF Amount</p>
                            <p class="text-sm font-semibold text-slate-800">{{ number_format($employee->fixed_nssf_amount, 2) }}</p>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- LST & Tax --}}
                <div class="card p-5">
                    <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2"><i class="fas fa-percent text-orange-500"></i> LST & Tax Settings</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        @php $bools = [
                            'Charge LST'           => $employee->charge_lst,
                            'LST Paid by Employer' => $employee->lst_paid_by_employer,
                            'Tax Paid by Employer' => $employee->tax_paid_by_employer,
                            'Apply Special Tax'    => $employee->apply_special_tax,
                        ]; @endphp
                        @foreach($bools as $lbl => $val)
                        <div class="flex items-center gap-2">
                            <span class="{{ $val ? 'text-green-500' : 'text-slate-300' }}"><i class="fas {{ $val ? 'fa-check-circle' : 'fa-times-circle' }}"></i></span>
                            <span class="text-sm text-slate-700">{{ $lbl }}</span>
                        </div>
                        @endforeach
                        @if($employee->special_tax_percentage)
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Special Tax %</p>
                            <p class="text-sm font-semibold text-slate-800">{{ $employee->special_tax_percentage }}%</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- SALARY & FUNDS --}}
            <div x-show="tab === 'salary'" class="space-y-4">
                {{-- Banking --}}
                <div class="card p-5">
                    <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2"><i class="fas fa-university text-blue-500"></i> Banking</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        @php $fields = [
                            'Bank Name'      => $employee->bank_name ?? '—',
                            'Account Number' => $employee->bank_account ?? '—',
                            'Bank Branch'    => $employee->bank_branch ?? '—',
                            'Payment Mode'   => ucfirst(str_replace('_',' ',$employee->payment_mode ?? '—')),
                        ]; @endphp
                        @foreach($fields as $lbl => $val)
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">{{ $lbl }}</p>
                            <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ $val }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- OT & Hours --}}
                <div class="card p-5">
                    <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2"><i class="fas fa-clock text-purple-500"></i> Hours & OT Calculation</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        @php $fields = [
                            'OT Calc Hours'          => $employee->ot_calc_hours ?? '—',
                            'Absenteeism Calc Hours' => $employee->absenteeism_calc_hours ?? '—',
                            'OT1 Calc Hours'         => $employee->ot1_calc_hours ?? '—',
                            'OT2 Calc Hours'         => $employee->ot2_calc_hours ?? '—',
                            'Min Daily Working Hrs'  => $employee->min_daily_working_hours ?? '—',
                        ]; @endphp
                        @foreach($fields as $lbl => $val)
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">{{ $lbl }}</p>
                            <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ $val }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Provident Fund --}}
                <div class="card p-5">
                    <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2"><i class="fas fa-piggy-bank text-green-500"></i> Provident Fund</h3>
                    <div class="mb-3">
                        <span class="{{ $employee->pf_applicable ? 'badge-green' : 'badge-gray' }}">{{ $employee->pf_applicable ? 'Applicable' : 'Not Applicable' }}</span>
                    </div>
                    @if($employee->pf_applicable)
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        @php $fields = [
                            'Deduction Type'   => $employee->pf_deduction_type ?? '—',
                            'Calculate On'     => $employee->pf_calculate_on ?? '—',
                            'Employee Rate'    => $employee->pf_employee_rate ? $employee->pf_employee_rate.'%' : '—',
                            'Employer Rate'    => $employee->pf_employer_rate ? $employee->pf_employer_rate.'%' : '—',
                            'Scheme'           => $employee->pf_scheme ?? '—',
                            'Voluntary Type'   => $employee->voluntary_pf_deduction_type ?? '—',
                            'Voluntary On'     => $employee->voluntary_pf_calculate_on ?? '—',
                            'Voluntary Amount' => $employee->voluntary_pf_amount ?? '—',
                        ]; @endphp
                        @foreach($fields as $lbl => $val)
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">{{ $lbl }}</p>
                            <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ $val }}</p>
                        </div>
                        @endforeach
                        <div class="flex items-center gap-2">
                            <span class="{{ $employee->do_not_deduct_voluntary_pf ? 'text-red-500' : 'text-green-500' }}"><i class="fas {{ $employee->do_not_deduct_voluntary_pf ? 'fa-times-circle' : 'fa-check-circle' }}"></i></span>
                            <span class="text-xs text-slate-600">Deduct Voluntary PF</span>
                        </div>
                    </div>
                    @endif
                </div>

                {{-- Pension --}}
                <div class="card p-5">
                    <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2"><i class="fas fa-hand-holding-usd text-yellow-500"></i> Pension</h3>
                    <div class="mb-3">
                        <span class="{{ $employee->pension_applicable ? 'badge-green' : 'badge-gray' }}">{{ $employee->pension_applicable ? 'Applicable' : 'Not Applicable' }}</span>
                    </div>
                    @if($employee->pension_applicable)
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        @php $fields = [
                            'Deduction Type'   => $employee->pension_deduction_type ?? '—',
                            'Calculate On'     => $employee->pension_calculate_on ?? '—',
                            'Employee Rate'    => $employee->pension_employee_rate ? $employee->pension_employee_rate.'%' : '—',
                            'Employer Rate'    => $employee->pension_employer_rate ? $employee->pension_employer_rate.'%' : '—',
                            'Scheme'           => $employee->pension_scheme ?? '—',
                            'Voluntary Type'   => $employee->voluntary_pension_deduction_type ?? '—',
                            'Voluntary On'     => $employee->voluntary_pension_calculate_on ?? '—',
                            'Voluntary Amount' => $employee->voluntary_pension_amount ?? '—',
                        ]; @endphp
                        @foreach($fields as $lbl => $val)
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">{{ $lbl }}</p>
                            <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ $val }}</p>
                        </div>
                        @endforeach
                        <div class="flex items-center gap-2">
                            <span class="{{ $employee->do_not_deduct_voluntary_pension ? 'text-red-500' : 'text-green-500' }}"><i class="fas {{ $employee->do_not_deduct_voluntary_pension ? 'fa-times-circle' : 'fa-check-circle' }}"></i></span>
                            <span class="text-xs text-slate-600">Deduct Voluntary Pension</span>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- STATUS & FLAGS --}}
            <div x-show="tab === 'flags'" class="space-y-4">
                {{-- Probation --}}
                <div class="card p-5">
                    <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2"><i class="fas fa-hourglass-half text-orange-500"></i> Probation</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Status</p>
                            <div class="mt-1">{!! $employee->probation_status_badge !!}</div>
                        </div>
                        @if($employee->probation_end_date)
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">End Date</p>
                            <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ $employee->probation_end_date->format('d M Y') }}</p>
                        </div>
                        @endif
                        @if($employee->probation_confirmed_at)
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Confirmed</p>
                            <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ $employee->probation_confirmed_at->format('d M Y') }}</p>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Blacklist --}}
                <div class="card p-5 {{ $employee->is_blacklisted ? 'border-red-200 bg-red-50' : '' }}">
                    <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-ban {{ $employee->is_blacklisted ? 'text-red-500' : 'text-slate-400' }}"></i> Blacklist
                        @if($employee->is_blacklisted)<span class="badge-red ml-2">BLACKLISTED</span>@endif
                    </h3>
                    @if($employee->is_blacklisted)
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Date</p>
                            <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ $employee->blacklist_date?->format('d M Y') ?? '—' }}</p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Reason</p>
                            <p class="text-sm text-slate-700 mt-0.5">{{ $employee->blacklist_reason ?? '—' }}</p>
                        </div>
                    </div>
                    @else
                    <p class="text-sm text-slate-400">Not blacklisted.</p>
                    @endif
                </div>

                {{-- On Hold --}}
                <div class="card p-5 {{ $employee->on_hold ? 'border-yellow-200 bg-yellow-50' : '' }}">
                    <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-pause-circle {{ $employee->on_hold ? 'text-yellow-500' : 'text-slate-400' }}"></i> Hold Status
                        @if($employee->on_hold)<span class="badge-yellow ml-2">ON HOLD</span>@endif
                    </h3>
                    @if($employee->on_hold)
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Hold Start Date</p>
                            <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ $employee->hold_date?->format('d M Y') ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Expected Return</p>
                            @if($employee->hold_end_date)
                                @php $daysLeft = now()->diffInDays($employee->hold_end_date, false); @endphp
                                <p class="text-sm font-semibold {{ $daysLeft < 0 ? 'text-red-600' : 'text-slate-800' }} mt-0.5">
                                    {{ $employee->hold_end_date->format('d M Y') }}
                                    <span class="text-xs font-normal {{ $daysLeft < 0 ? 'text-red-500' : 'text-slate-400' }}">
                                        ({{ $daysLeft < 0 ? abs((int)$daysLeft).' day(s) overdue' : (int)$daysLeft.' day(s) left' }})
                                    </span>
                                </p>
                            @else
                                <p class="text-sm text-slate-400 mt-0.5">Not specified</p>
                            @endif
                        </div>
                        <div class="col-span-2">
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Reason</p>
                            <p class="text-sm text-slate-700 mt-0.5">{{ $employee->hold_reason ?? '—' }}</p>
                        </div>
                    </div>
                    @else
                    <p class="text-sm text-slate-400">Not on hold.</p>
                    @endif
                </div>
            </div>

            {{-- LEAVE BALANCES --}}
            <div x-show="tab === 'leave'">
                <div class="card p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-slate-800 flex items-center gap-2"><i class="fas fa-umbrella-beach text-blue-500"></i> Leave Balances — {{ now()->year }}</h3>
                    </div>
                    @php $balances = $employee->leaveBalances->where('year', now()->year); @endphp
                    @if($balances->count())
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($balances as $bal)
                        @php
                            $used = $bal->used_days ?? 0;
                            $total = $bal->total_days ?? 0;
                            $remaining = $total - $used;
                            $pct = $total > 0 ? min(100, ($used/$total)*100) : 0;
                            $color = $pct > 80 ? 'bg-red-500' : ($pct > 50 ? 'bg-yellow-400' : 'bg-blue-500');
                        @endphp
                        <div class="rounded-xl p-4 border border-slate-200 bg-slate-50">
                            <p class="text-xs font-semibold text-slate-500 uppercase">{{ $bal->leaveType?->name ?? 'Unknown' }}</p>
                            <div class="flex items-end gap-2 mt-2">
                                <span class="text-2xl font-bold text-slate-900">{{ $remaining }}</span>
                                <span class="text-xs text-slate-400 mb-1">/ {{ $total }} days left</span>
                            </div>
                            <div class="mt-2 bg-slate-200 rounded-full h-1.5">
                                <div class="{{ $color }} h-1.5 rounded-full" style="width:{{ $pct }}%"></div>
                            </div>
                            <p class="text-xs text-slate-400 mt-1">{{ $used }} used &bull; {{ $bal->pending_days ?? 0 }} pending</p>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div class="text-center py-10 text-slate-400">
                        <i class="fas fa-umbrella-beach text-3xl mb-3 text-slate-200"></i>
                        <p class="text-sm">No leave balances for {{ now()->year }}.</p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- EMPLOYMENT HISTORY --}}
            <div x-show="tab === 'history'">
                <div class="card p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-slate-800 flex items-center gap-2"><i class="fas fa-history text-blue-500"></i> Employment History</h3>
                        <a href="{{ route('employees.history', $employee) }}" class="btn-secondary text-xs"><i class="fas fa-plus"></i> Add Entry</a>
                    </div>
                    @if($employee->history->count())
                    <div class="relative pl-6 border-l-2 border-slate-200 space-y-5">
                        @foreach($employee->history as $hist)
                        <div class="relative">
                            <div class="absolute -left-[1.375rem] w-3 h-3 bg-blue-500 rounded-full border-2 border-white top-1.5"></div>
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">{{ $hist->position }}</p>
                                    <p class="text-xs text-slate-500 mt-0.5">
                                        {{ $hist->start_date?->format('M Y') }} {{ $hist->end_date ? '— '.$hist->end_date->format('M Y') : '— Present' }}
                                        @if($hist->company_name) &bull; {{ $hist->company_name }}@endif
                                    </p>
                                    @if($hist->reason_for_change)<p class="text-xs text-slate-400 mt-0.5 italic">{{ $hist->reason_for_change }}</p>@endif
                                </div>
                                <span class="badge-{{ $hist->type === 'internal' ? 'blue' : 'gray' }} ml-4 shrink-0">{{ ucfirst($hist->type ?? 'external') }}</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div class="text-center py-10 text-slate-400">
                        <i class="fas fa-history text-3xl mb-3 text-slate-200"></i>
                        <p class="text-sm">No employment history recorded.</p>
                        <a href="{{ route('employees.history', $employee) }}" class="mt-3 inline-block btn-primary text-xs">Add First Entry</a>
                    </div>
                    @endif
                </div>
            </div>

        </div>{{-- end main content --}}
    </div>{{-- end grid --}}
</div>
@endsection
