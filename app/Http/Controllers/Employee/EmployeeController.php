<?php
namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EmployeeDocument;
use App\Models\EmploymentHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::with(["department", "designation", "user"])
            ->withTrashed(false);

        if ($request->filled("department_id")) {
            $query->where("department_id", $request->department_id);
        }
        if ($request->filled("status")) {
            $query->where("status", $request->status);
        }
        if ($request->filled("search")) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where("first_name", "like", "%$s%")
                  ->orWhere("last_name", "like", "%$s%")
                  ->orWhere("emp_number", "like", "%$s%");
            });
        }
        if ($request->filled("employment_type")) {
            $query->where("employment_type", $request->employment_type);
        }

        if ($request->ajax()) {
            $employees = $query->orderBy("first_name")->paginate(25);
            return response()->json($employees);
        }

        $employees   = $query->orderBy("first_name")->paginate(25);
        $departments = Department::where("is_active", true)->orderBy("name")->get();
        return view("employees.index", compact("employees", "departments"));
    }

    public function create()
    {
        $departments  = Department::where("is_active", true)->orderBy("name")->get();
        $designations = Designation::where("is_active", true)->orderBy("title")->get();
        $managers     = Employee::where("status", "active")->orderBy("first_name")->get();
        return view("employees.create", compact("departments", "designations", "managers"));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            "first_name"       => "required|string|max:100",
            "last_name"        => "required|string|max:100",
            "email"            => "required|email|unique:users,email",
            "phone"            => "nullable|string|max:20",
            "department_id"    => "required|exists:departments,id",
            "designation_id"   => "required|exists:designations,id",
            "manager_id"       => "nullable|exists:employees,id",
            "hire_date"        => "required|date",
            "employment_type"  => "required|in:full_time,part_time,contract,intern",
            "date_of_birth"    => "nullable|date",
            "gender"           => "nullable|in:male,female,other",
            "national_id"      => "nullable|string|max:50",
            "address"          => "nullable|string",
            "city"             => "nullable|string|max:100",
            "salary_grade"     => "nullable|string|max:20",
            "bank_name"        => "nullable|string",
            "bank_account"     => "nullable|string|max:50",
            "tax_number"       => "nullable|string|max:50",
        ]);

        DB::beginTransaction();
        try {
            // Create user account
            $user = User::create([
                "name"     => $data["first_name"] . " " . $data["last_name"],
                "email"    => $request->email,
                "password" => Hash::make("Employee@1234"),
                "status"   => "active",
            ]);
            $user->assignRole("employee");

            // Generate employee number
            $year      = date("Y");
            $count     = Employee::whereYear("created_at", $year)->count() + 1;
            $empNumber = "EMP-" . $year . "-" . str_pad($count, 4, "0", STR_PAD_LEFT);

            $employee = Employee::create(array_merge($data, [
                "user_id"    => $user->id,
                "emp_number" => $empNumber,
                "status"     => "active",
            ]));

            // Record in employment history
            EmploymentHistory::create([
                "employee_id"       => $employee->id,
                "position"          => Designation::find($data["designation_id"])?->title ?? "",
                "department_id"     => $data["department_id"],
                "start_date"        => $data["hire_date"],
                "type"              => "hire",
                "reason_for_change" => "Initial hire",
                "recorded_by"       => auth()->id(),
            ]);

            DB::commit();
            return redirect()->route("employees.show", $employee)->with("success", "Employee created successfully. Login: {$request->email} / Employee@1234");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors(["error" => "Failed to create employee: " . $e->getMessage()]);
        }
    }

    public function show(Employee $employee)
    {
        $employee->load(["department", "designation", "manager", "user", "documents", "history", "leaveBalances.leaveType"]);
        return view("employees.show", compact("employee"));
    }

    public function edit(Employee $employee)
    {
        $departments  = Department::where("is_active", true)->orderBy("name")->get();
        $designations = Designation::where("is_active", true)->orderBy("title")->get();
        $managers     = Employee::where("status", "active")->where("id", "!=", $employee->id)->orderBy("first_name")->get();
        return view("employees.edit", compact("employee", "departments", "designations", "managers"));
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate([
            "first_name"      => "required|string|max:100",
            "last_name"       => "required|string|max:100",
            "phone"           => "nullable|string|max:20",
            "department_id"   => "required|exists:departments,id",
            "designation_id"  => "required|exists:designations,id",
            "manager_id"      => "nullable|exists:employees,id",
            "hire_date"       => "required|date",
            "employment_type" => "required|in:full_time,part_time,contract,intern",
            "status"          => "required|in:active,on_leave,terminated,suspended",
            "date_of_birth"   => "nullable|date",
            "gender"          => "nullable|in:male,female,other",
            "national_id"     => "nullable|string|max:50",
            "address"         => "nullable|string",
            "city"            => "nullable|string|max:100",
            "salary_grade"    => "nullable|string|max:20",
            "bank_name"       => "nullable|string",
            "bank_account"    => "nullable|string|max:50",
            "tax_number"      => "nullable|string|max:50",
            "bio"             => "nullable|string",
        ]);

        $employee->update($data);

        // Sync user name
        $employee->user?->update(["name" => $data["first_name"] . " " . $data["last_name"]]);

        return redirect()->route("employees.show", $employee)->with("success", "Employee updated successfully.");
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();
        return redirect()->route("employees.index")->with("success", "Employee archived.");
    }

    public function documents(Employee $employee)
    {
        $documents = $employee->documents()->orderByDesc("created_at")->get();
        return view("employees.documents", compact("employee", "documents"));
    }

    public function storeDocument(Request $request, Employee $employee)
    {
        $data = $request->validate([
            "document_type" => "required|string|max:100",
            "title"         => "required|string|max:255",
            "file"          => "required|file|max:10240",
            "expiry_date"   => "nullable|date",
            "notes"         => "nullable|string",
        ]);

        $file = $request->file("file");
        $path = $file->store("employee-docs/{$employee->id}", "public");

        $employee->documents()->create([
            "document_type" => $data["document_type"],
            "title"         => $data["title"],
            "file_path"     => $path,
            "file_name"     => $file->getClientOriginalName(),
            "mime_type"     => $file->getMimeType(),
            "expiry_date"   => $data["expiry_date"] ?? null,
            "notes"         => $data["notes"] ?? null,
            "uploaded_by"   => auth()->id(),
        ]);

        return back()->with("success", "Document uploaded.");
    }

    public function destroyDocument(EmployeeDocument $document)
    {
        Storage::disk("public")->delete($document->file_path);
        $document->delete();
        return back()->with("success", "Document deleted.");
    }

    public function history(Employee $employee)
    {
        $history = $employee->history()->with("employee")->get();
        return view("employees.history", compact("employee", "history"));
    }
}
