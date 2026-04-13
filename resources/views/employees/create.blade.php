@extends("layouts.app")
@section("title","Add Employee")
@section("breadcrumb")
<a href="{{ route('employees.index') }}" class="text-slate-500 hover:text-slate-700 text-sm">Employees</a>
<i class="fas fa-chevron-right text-slate-300 text-xs mx-1"></i>
<span class="text-slate-600 text-sm font-medium">Add Employee</span>
@endsection
@section("content")
<x-page-header title="Add New Employee" subtitle="Fill in the employee details below">
    <a href="{{ route('employees.index') }}" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</x-page-header>

<form method="POST" action="{{ route('employees.store') }}" class="space-y-6">
    @csrf
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Personal Information --}}
        <div class="lg:col-span-2 card p-6">
            <h3 class="text-base font-semibold text-slate-800 mb-4 flex items-center gap-2"><i class="fas fa-user text-blue-500"></i> Personal Information</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">First Name <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Last Name <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Email Address <span class="text-red-500">*</span></label>
                    <input type="email" name="email" value="{{ old('email') }}" class="form-input" required>
                    <p class="text-xs text-slate-400 mt-1">This will be the login email. Default password: Employee@1234</p>
                </div>
                <div>
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" class="form-input" placeholder="+27 ...">
                </div>
                <div>
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">Select...</option>
                        <option value="male" {{ old('gender')=='male'?'selected':'' }}>Male</option>
                        <option value="female" {{ old('gender')=='female'?'selected':'' }}>Female</option>
                        <option value="other" {{ old('gender')=='other'?'selected':'' }}>Other</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">National ID / Passport</label>
                    <input type="text" name="national_id" value="{{ old('national_id') }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">City</label>
                    <input type="text" name="city" value="{{ old('city') }}" class="form-input">
                </div>
                <div class="col-span-2">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-input" rows="2">{{ old('address') }}</textarea>
                </div>
            </div>
        </div>

        {{-- Job Information --}}
        <div class="space-y-4">
            <div class="card p-6">
                <h3 class="text-base font-semibold text-slate-800 mb-4 flex items-center gap-2"><i class="fas fa-briefcase text-blue-500"></i> Job Details</h3>
                <div class="space-y-3">
                    <div>
                        <label class="form-label">Department <span class="text-red-500">*</span></label>
                        <select name="department_id" id="deptSelect" class="form-select select2" required>
                            <option value="">Select department</option>
                            @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ old('department_id')==$dept->id?'selected':'' }}>{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Designation <span class="text-red-500">*</span></label>
                        <select name="designation_id" class="form-select select2" required>
                            <option value="">Select designation</option>
                            @foreach($designations as $d)
                            <option value="{{ $d->id }}" {{ old('designation_id')==$d->id?'selected':'' }}>{{ $d->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Reports To (Manager)</label>
                        <select name="manager_id" class="form-select select2">
                            <option value="">None</option>
                            @foreach($managers as $m)
                            <option value="{{ $m->id }}" {{ old('manager_id')==$m->id?'selected':'' }}>{{ $m->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Employment Type <span class="text-red-500">*</span></label>
                        <select name="employment_type" class="form-select" required>
                            <option value="full_time" {{ old('employment_type','full_time')=='full_time'?'selected':'' }}>Full Time</option>
                            <option value="part_time" {{ old('employment_type')=='part_time'?'selected':'' }}>Part Time</option>
                            <option value="contract" {{ old('employment_type')=='contract'?'selected':'' }}>Contract</option>
                            <option value="intern" {{ old('employment_type')=='intern'?'selected':'' }}>Intern</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Hire Date <span class="text-red-500">*</span></label>
                        <input type="date" name="hire_date" value="{{ old('hire_date', date('Y-m-d')) }}" class="form-input" required>
                    </div>
                    <div>
                        <label class="form-label">Salary Grade</label>
                        <input type="text" name="salary_grade" value="{{ old('salary_grade') }}" class="form-input" placeholder="e.g. G4">
                    </div>
                </div>
            </div>

            <div class="card p-6">
                <h3 class="text-base font-semibold text-slate-800 mb-4 flex items-center gap-2"><i class="fas fa-university text-blue-500"></i> Banking</h3>
                <div class="space-y-3">
                    <div>
                        <label class="form-label">Bank Name</label>
                        <input type="text" name="bank_name" value="{{ old('bank_name') }}" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Account Number</label>
                        <input type="text" name="bank_account" value="{{ old('bank_account') }}" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Tax Number</label>
                        <input type="text" name="tax_number" value="{{ old('tax_number') }}" class="form-input">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="btn-primary px-8"><i class="fas fa-save"></i> Create Employee</button>
        <a href="{{ route('employees.index') }}" class="btn-secondary">Cancel</a>
    </div>
</form>
@endsection
