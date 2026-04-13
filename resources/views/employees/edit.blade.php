@extends('layouts.app')
@section('title', 'Edit Employee')
@section('content')

<x-page-header title="Edit Employee" :subtitle="$employee->emp_number . ' — ' . $employee->full_name">
    <a href="{{ route('employees.show', $employee) }}" class="btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</x-page-header>

@if($errors->any())
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
    <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<form method="POST" action="{{ route('employees.update', $employee) }}" enctype="multipart/form-data">
    @csrf @method('PUT')

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- Left / Main --}}
        <div class="xl:col-span-2 space-y-6">

            {{-- Personal --}}
            <div class="card p-6">
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-5 flex items-center gap-2">
                    <i class="fas fa-user text-blue-500"></i> Personal Information
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="form-label">First Name <span class="text-red-500">*</span></label>
                        <input type="text" name="first_name" class="form-input" required value="{{ old('first_name', $employee->first_name) }}">
                    </div>
                    <div>
                        <label class="form-label">Last Name <span class="text-red-500">*</span></label>
                        <input type="text" name="last_name" class="form-input" required value="{{ old('last_name', $employee->last_name) }}">
                    </div>
                    <div>
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-input" value="{{ old('phone', $employee->phone) }}">
                    </div>
                    <div>
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-input">
                            <option value="">— Select —</option>
                            @foreach(['male'=>'Male','female'=>'Female','other'=>'Other'] as $val=>$label)
                            <option value="{{ $val }}" @selected(old('gender',$employee->gender)===$val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-input" value="{{ old('date_of_birth', $employee->date_of_birth?->format('Y-m-d')) }}">
                    </div>
                    <div>
                        <label class="form-label">National ID</label>
                        <input type="text" name="national_id" class="form-input" value="{{ old('national_id', $employee->national_id) }}">
                    </div>
                    <div class="sm:col-span-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" rows="2" class="form-input">{{ old('address', $employee->address) }}</textarea>
                    </div>
                    <div class="sm:col-span-3">
                        <label class="form-label">Profile Photo</label>
                        <input type="file" name="photo" accept="image/*"
                               class="block w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-slate-200 rounded-lg p-1 cursor-pointer">
                        @if($employee->user?->avatar)
                        <p class="mt-1 text-xs text-slate-500">Current: <span class="text-blue-600">{{ basename($employee->user->avatar) }}</span></p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Job --}}
            <div class="card p-6">
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-5 flex items-center gap-2">
                    <i class="fas fa-briefcase text-blue-500"></i> Job Information
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Department <span class="text-red-500">*</span></label>
                        <select name="department_id" id="department_id" class="form-input select2" required>
                            <option value="">— Select Department —</option>
                            @foreach($departments as $d)
                            <option value="{{ $d->id }}" @selected(old('department_id',$employee->department_id)==$d->id)>{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Designation <span class="text-red-500">*</span></label>
                        <select name="designation_id" id="designation_id" class="form-input select2" required>
                            <option value="">— Select Designation —</option>
                            @foreach($designations as $d)
                            <option value="{{ $d->id }}" @selected(old('designation_id',$employee->designation_id)==$d->id)>{{ $d->title }}</option>
                            @endforeach
                        </select>
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
                    <div>
                        <label class="form-label">Hire Date</label>
                        <input type="date" name="hire_date" class="form-input" value="{{ old('hire_date', $employee->hire_date?->format('Y-m-d')) }}">
                    </div>
                    <div>
                        <label class="form-label">Employment Type</label>
                        <select name="employment_type" class="form-input" id="employment_type_sel">
                            <option value="full_time"  @selected(old('employment_type',$employee->employment_type)==='full_time')>Full Time</option>
                            <option value="part_time"  @selected(old('employment_type',$employee->employment_type)==='part_time')>Part Time</option>
                            <option value="contract"   @selected(old('employment_type',$employee->employment_type)==='contract')>Contract</option>
                            <option value="intern"     @selected(old('employment_type',$employee->employment_type)==='intern')>Intern</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Status</label>
                        <select name="status" class="form-input" id="status_sel">
                            <option value="active"     @selected(old('status',$employee->status)==='active')>Active</option>
                            <option value="on_leave"   @selected(old('status',$employee->status)==='on_leave')>On Leave</option>
                            <option value="terminated" @selected(old('status',$employee->status)==='terminated')>Terminated</option>
                            <option value="suspended"  @selected(old('status',$employee->status)==='suspended')>Suspended</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Salary Grade</label>
                        <select name="salary_grade" class="form-input" id="salary_grade_sel">
                            <option value="">— None —</option>
                            @foreach($grades as $g)
                            <option value="{{ $g->grade }}" @selected(old('salary_grade',$employee->salary_grade)===$g->grade)>{{ $g->grade }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

        </div>

        {{-- Right / Sidebar --}}
        <div class="space-y-5">

            {{-- Avatar --}}
            <div class="card p-5 text-center">
                <img src="{{ $employee->avatar_url }}" alt="{{ $employee->full_name }}"
                     class="w-20 h-20 rounded-full mx-auto object-cover mb-3 ring-2 ring-blue-100">
                <p class="font-semibold text-slate-800 text-sm">{{ $employee->full_name }}</p>
                <p class="text-xs text-slate-500 mt-0.5">{{ $employee->emp_number }}</p>
                <p class="text-xs text-slate-400 mt-0.5">{{ $employee->department?->name ?? 'No Department' }}</p>
            </div>

            {{-- Banking --}}
            <div class="card p-5">
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                    <i class="fas fa-university text-green-500"></i> Banking Details
                </h3>
                <div class="space-y-3">
                    <div>
                        <label class="form-label">Bank Name</label>
                        <input type="text" name="bank_name" class="form-input" placeholder="e.g. Standard Bank" value="{{ old('bank_name', $employee->bank_name) }}">
                    </div>
                    <div>
                        <label class="form-label">Account Number</label>
                        <input type="text" name="bank_account" class="form-input" placeholder="e.g. 0123456789" value="{{ old('bank_account', $employee->bank_account) }}">
                    </div>
                    <div>
                        <label class="form-label">Branch Code</label>
                        <input type="text" name="bank_branch" class="form-input" placeholder="e.g. 051001" value="{{ old('bank_branch', $employee->bank_branch) }}">
                    </div>
                    <div>
                        <label class="form-label">Tax Number</label>
                        <input type="text" name="tax_number" class="form-input" placeholder="SARS tax number" value="{{ old('tax_number', $employee->tax_number) }}">
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="card p-5 space-y-3">
                <button type="submit" class="btn-primary w-full justify-center">
                    <i class="fas fa-save"></i> Update Employee
                </button>
                <a href="{{ route('employees.show', $employee) }}" class="btn-secondary w-full justify-center">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>

        </div>
    </div>
</form>

@push('scripts')
<script>
// Prevent Select2 from being applied to simple native selects
$(document).ready(function(){
    // Only #employment_type_sel, #status_sel, #salary_grade_sel remain as native selects
    // Select2 for department, designation, manager is already handled by global init
});
</script>
@endpush

@endsection
