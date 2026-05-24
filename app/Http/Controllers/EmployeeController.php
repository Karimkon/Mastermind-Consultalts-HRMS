<?php
namespace App\Http\Controllers;

use App\Models\{Employee, Department, Designation, User, LeaveRequest, LeaveBalance};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, Storage, DB};
use Spatie\Permission\Models\Role;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $employees = Employee::with(['department', 'designation', 'user'])
            ->when($request->search, fn($q) => $q->whereHas('user', fn($u) => $u->where('name', 'like', "%{$request->search}%"))->orWhere('emp_number', 'like', "%{$request->search}%"))
            ->when($request->department_id, fn($q) => $q->where('department_id', $request->department_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->type, fn($q) => $q->where('employment_type', $request->type))
            ->paginate(25);
        $departments = Department::orderBy('name')->get();
        return view('employees.index', compact('employees', 'departments'));
    }

    public function create()
    {
        $departments  = Department::orderBy('name')->get();
        $designations = Designation::orderBy('title')->get();
        $managers     = Employee::with('user')->where('status', 'active')->get();
        $grades       = \App\Models\SalaryGrade::orderBy('grade')->get();
        return view('employees.create', compact('departments', 'designations', 'managers', 'grades'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name'        => 'required|string|max:100',
            'last_name'         => 'required|string|max:100',
            'email'             => 'required|email|unique:users,email',
            'department_id'     => 'required|exists:departments,id',
            'designation_id'    => 'required|exists:designations,id',
            'hire_date'         => 'required|date',
        ]);

        DB::transaction(function () use ($request) {
            $user = User::create([
                'name'     => $request->first_name.' '.$request->last_name,
                'email'    => $request->email,
                'password' => Hash::make($request->password ?? 'Password@123'),
            ]);
            $user->assignRole('employee');

            $empNo = $this->generateEmpNumber($request->client_id ?? null);

            $employee = Employee::create([
                'user_id'         => $user->id,
                'emp_number'      => $empNo,
                'first_name'      => $request->first_name,
                'last_name'       => $request->last_name,
                'department_id'   => $request->department_id,
                'designation_id'  => $request->designation_id,
                'manager_id'      => $request->manager_id,
                'hire_date'       => $request->hire_date,
                'employment_type' => $request->employment_type ?? 'full_time',
                'status'          => 'active',
                'phone'           => $request->phone,
                'gender'          => $request->gender,
                'date_of_birth'   => $request->date_of_birth,
                'national_id'     => $request->national_id,
                'address'         => $request->address,
                'bank_name'       => $request->bank_name,
                'bank_account'    => $request->bank_account,
                'salary_grade'    => $request->salary_grade,
            ]);

            if ($request->hasFile('photo')) {
                $path = $request->file('photo')->store('employees/photos', 'public');
                $user->update(['avatar' => $path]);
            }
        });

        return redirect()->route('employees.index')->with('success', 'Employee created successfully.');
    }

    public function show(Employee $employee)
    {
        $employee->load(['department', 'designation', 'manager', 'user', 'documents', 'history', 'leaveBalances.leaveType', 'clients', 'clientTransfers.client']);
        return view('employees.show', compact('employee'));
    }

    public function edit(Employee $employee)
    {
        $departments  = Department::orderBy('name')->get();
        $designations = Designation::orderBy('title')->get();
        $managers     = Employee::with('user')->where('status', 'active')->where('id', '!=', $employee->id)->get();
        $grades       = \App\Models\SalaryGrade::orderBy('grade')->get();
        return view('employees.edit', compact('employee', 'departments', 'designations', 'managers', 'grades'));
    }

    public function update(Request $request, Employee $employee)
    {
        $request->validate([
            'first_name'     => 'required|string|max:100',
            'last_name'      => 'required|string|max:100',
            'department_id'  => 'required|exists:departments,id',
            'designation_id' => 'required|exists:designations,id',
        ]);

        $fields = [
            // Identity
            'title','middle_name','payroll_number',
            'first_name','last_name',
            // Job
            'department_id','designation_id','manager_id',
            'hire_date','end_date','employment_type','status','salary_grade',
            // Job Profile
            'week_off_type','week_off_day','holiday_calendar','contract_start_date','contract_notes',
            // Placement
            'work_location','sub_department','employee_category','class_name','position_name','organization_unit',
            // Personal
            'phone','personal_email','date_of_birth','gender','marital_status',
            'children_count','dependents_count','anniversary_date',
            'national_id','passport_number','address','city','country',
            'religion','nationality','mother_tongue','bio',
            // Emergency & NOK
            'emergency_contact_name','emergency_contact_phone',
            'next_of_kin_name','next_of_kin_relation','next_of_kin_phone','next_of_kin_email',
            // Insurance & Statutory
            'nssf_number','tin_number','ifms_supplier_no','pension_no',
            'fixed_nssf_amount','voluntary_nssf','special_tax_percentage',
            // Banking & Calculation
            'bank_name','bank_account','bank_branch','tax_number','payment_mode',
            'ot_calc_hours','absenteeism_calc_hours','ot1_calc_hours','ot2_calc_hours','min_daily_working_hours',
            // PF
            'pf_deduction_type','pf_calculate_on','pf_employee_rate','pf_employer_rate','pf_scheme',
            'voluntary_pf_deduction_type','voluntary_pf_calculate_on','voluntary_pf_amount',
            // Pension
            'pension_deduction_type','pension_calculate_on','pension_employee_rate','pension_employer_rate','pension_scheme',
            'voluntary_pension_deduction_type','voluntary_pension_calculate_on','voluntary_pension_amount',
            // Blacklist & Hold
            'blacklist_date','blacklist_reason','hold_date','hold_end_date','hold_reason',
        ];

        $data = $request->only($fields);

        // Booleans (checkboxes send nothing when unchecked)
        foreach ([
            'contract_applicable','is_expatriate','insurance_relief',
            'charge_nssf','force_fixed_nssf','nssf_paid_by_employer','do_not_charge_nssf_employee',
            'charge_lst','lst_paid_by_employer','tax_paid_by_employer',
            'apply_special_tax','pf_applicable','do_not_deduct_voluntary_pf',
            'pension_applicable','do_not_deduct_voluntary_pension',
            'is_blacklisted','on_hold',
        ] as $bool) {
            $data[$bool] = $request->boolean($bool);
        }

        $previousStatus = $employee->status;
        $employee->update($data);
        $employee->user->update(['name' => trim(($request->first_name ?? '').' '.($request->last_name ?? ''))]);

        // When employee is terminated or suspended, cancel all pending/approved future leaves
        if (in_array($data['status'] ?? '', ['terminated', 'suspended']) && $previousStatus !== ($data['status'] ?? '')) {
            $today = now()->toDateString();

            // Cancel pending leaves
            $employee->leaveRequests()->where('status', 'pending')->update(['status' => 'cancelled']);

            // Reject approved future leaves and restore leave balances
            $futureApproved = $employee->leaveRequests()
                ->where('status', 'approved')
                ->where('from_date', '>', $today)
                ->get();

            foreach ($futureApproved as $leave) {
                $leave->update(['status' => 'cancelled', 'rejection_reason' => 'Employee ' . ($data['status'] ?? '') . ' — leave auto-cancelled.']);
                LeaveBalance::where('employee_id', $employee->id)
                    ->where('leave_type_id', $leave->leave_type_id)
                    ->where('year', $leave->from_date->year)
                    ->decrement('used_days', $leave->days_count);
            }
        }

        if ($request->hasFile('photo')) {
            if ($employee->user?->avatar) Storage::disk('public')->delete($employee->user->avatar);
            $path = $request->file('photo')->store('employees/photos', 'public');
            $employee->user->update(['avatar' => $path]);
        }

        // If salary_grade changed and employee has no current salary record, seed one from grade's basic_min
        if (!empty($data['salary_grade'])) {
            $hasCurrentSalary = \App\Models\EmployeeSalary::where('employee_id', $employee->id)->where('is_current', true)->exists();
            if (!$hasCurrentSalary) {
                $grade = \App\Models\SalaryGrade::where('grade', $data['salary_grade'])->first();
                if ($grade) {
                    \App\Models\EmployeeSalary::create([
                        'employee_id'    => $employee->id,
                        'basic_salary'   => $grade->basic_min,
                        'components'     => [],
                        'effective_from' => $employee->hire_date ?? now()->toDateString(),
                        'is_current'     => true,
                    ]);
                }
            }
        }

        // Contract document upload
        if ($request->hasFile('contract_file')) {
            $file     = $request->file('contract_file');
            $path     = $file->store("employees/contracts/{$employee->id}", 'public');
            $endDate  = $data['end_date'] ?? $employee->end_date?->format('Y-m-d');

            // Replace any existing contract document
            $employee->documents()->where('document_type', 'contract')->delete();

            $employee->documents()->create([
                'document_type' => 'contract',
                'title'         => 'Employment Contract — ' . ($employee->full_name),
                'file_path'     => $path,
                'file_name'     => $file->getClientOriginalName(),
                'mime_type'     => $file->getMimeType(),
                'expiry_date'   => $endDate ?: null,
                'notes'         => $request->contract_notes,
                'uploaded_by'   => auth()->id(),
            ]);
        }

        return redirect()->route('employees.show', $employee)->with('success', 'Employee profile updated.');
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();
        return redirect()->route('employees.index')->with('success', 'Employee removed.');
    }

    private function generateEmpNumber(?int $clientId): string
    {
        if ($clientId) {
            $client = \App\Models\Client::find($clientId);
            if ($client) {
                // Build abbreviation from company name (up to 3 uppercase letters)
                $words  = preg_split('/\s+/', $client->company_name);
                $abbr   = '';
                foreach ($words as $w) {
                    if (strlen($abbr) >= 3) break;
                    if (ctype_alpha($w[0] ?? '')) $abbr .= strtoupper($w[0]);
                }
                $abbr = $abbr ?: strtoupper(substr(preg_replace('/[^A-Za-z]/','',$client->company_name), 0, 3));
                // Count existing employees for this client
                $count = $client->employees()->withTrashed()->count() + 1;
                return $abbr . str_pad($count, 3, '0', STR_PAD_LEFT);
            }
        }
        // Fallback: MM (Mastermind) + sequential
        $count = Employee::withTrashed()->count() + 1;
        return 'MM' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    public function documents(Employee $employee)
    {
        $employee->load('documents');
        return view('employees.documents', compact('employee'));
    }

    public function storeDocument(Request $request, Employee $employee)
    {
        $request->validate(['type' => 'required', 'file' => 'required|file|max:10240']);
        $path = $request->file('file')->store('employees/documents', 'public');
        $employee->documents()->create(['type' => $request->type, 'file_path' => $path, 'expiry_date' => $request->expiry_date]);
        return back()->with('success', 'Document uploaded.');
    }

    public function history(Employee $employee)
    {
        $employee->load('employmentHistory');
        return view('employees.history', compact('employee'));
    }

    public function storeHistory(Request $request, Employee $employee)
    {
        $request->validate(['position' => 'required|string']);
        $employee->history()->create([
            'position'          => $request->position,
            'company_name'      => $request->company_name,
            'start_date'        => $request->start_date,
            'end_date'          => $request->end_date,
            'reason_for_change' => $request->reason_for_change,
            'type'              => $request->type ?? 'internal',
            'recorded_by'       => auth()->id(),
        ]);
        return back()->with('success', 'History entry added.');
    }
}