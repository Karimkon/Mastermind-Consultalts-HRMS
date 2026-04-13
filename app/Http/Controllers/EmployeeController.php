<?php
namespace App\Http\Controllers;

use App\Models\{Employee, Department, Designation, User};
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

            $empNo = 'EMP'.str_pad(Employee::count() + 1, 4, '0', STR_PAD_LEFT);

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
        $employee->load(['department', 'designation', 'manager', 'user', 'documents', 'employmentHistory', 'leaveBalances.leaveType']);
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

        $employee->update($request->only('first_name','last_name','department_id','designation_id','manager_id','phone','gender','date_of_birth','national_id','address','hire_date','employment_type','status','salary_grade','bank_name','bank_account','bank_branch','tax_number'));
        $employee->user->update(['name' => $request->first_name.' '.$request->last_name]);

        if ($request->hasFile('photo')) {
            if ($employee->user?->avatar) Storage::disk('public')->delete($employee->user->avatar);
            $path = $request->file('photo')->store('employees/photos', 'public');
            $employee->user->update(['avatar' => $path]);
        }

        return redirect()->route('employees.show', $employee)->with('success', 'Employee updated.');
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();
        return redirect()->route('employees.index')->with('success', 'Employee removed.');
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
        $employee->employmentHistory()->create($request->only('position','department','start_date','end_date','reason'));
        return back()->with('success', 'History entry added.');
    }
}