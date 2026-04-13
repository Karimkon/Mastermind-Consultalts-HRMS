<?php
namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\SalaryGrade;
use App\Models\SalaryComponent;
use Illuminate\Http\Request;

class SalaryController extends Controller
{
    public function index() {
        $salaries = EmployeeSalary::with("employee.department")->where("is_current",true)->paginate(25);
        return view("payroll.salary.index", compact("salaries"));
    }
    public function create() {
        $employees  = Employee::where("status","active")->orderBy("first_name")->get();
        $grades     = SalaryGrade::orderBy("grade")->get();
        $components = SalaryComponent::where("is_active",true)->get();
        return view("payroll.salary.create", compact("employees","grades","components"));
    }
    public function store(Request $request) {
        $data = $request->validate(["employee_id" => "required|exists:employees,id", "basic_salary" => "required|numeric|min:0", "effective_from" => "required|date"]);
        EmployeeSalary::where("employee_id",$data["employee_id"])->where("is_current",true)->update(["is_current" => false]);
        EmployeeSalary::create(array_merge($data, ["components" => $request->components ?? [], "is_current" => true, "created_by" => auth()->id()]));
        return redirect()->route("salary.index")->with("success","Salary assigned.");
    }
    public function show(EmployeeSalary $salary) { return view("payroll.salary.show", compact("salary")); }
    public function edit(EmployeeSalary $salary) {
        $components = SalaryComponent::where("is_active",true)->get();
        return view("payroll.salary.edit", compact("salary","components"));
    }
    public function update(Request $request, EmployeeSalary $salary) {
        $salary->update($request->validate(["basic_salary" => "required|numeric|min:0", "effective_from" => "required|date"]));
        return back()->with("success","Salary updated.");
    }
    public function destroy(EmployeeSalary $salary) { $salary->delete(); return back()->with("success","Deleted."); }

    public function grades() {
        $grades = SalaryGrade::orderBy("grade")->get();
        return view("payroll.salary.grades", compact("grades"));
    }
    public function storeGrade(Request $request) {
        $data = $request->validate(["grade" => "required|string|max:20|unique:salary_grades", "label" => "required|string|max:100", "basic_min" => "required|numeric", "basic_max" => "required|numeric|gte:basic_min"]);
        SalaryGrade::create($data);
        return back()->with("success","Grade created.");
    }

    public function components() {
        $components = SalaryComponent::orderBy("type")->orderBy("name")->get();
        return view("payroll.salary.components", compact("components"));
    }
    public function storeComponent(Request $request) {
        $data = $request->validate(["name" => "required|string|max:100", "code" => "required|string|max:30|unique:salary_components", "type" => "required|in:allowance,deduction", "is_taxable" => "boolean", "is_fixed" => "boolean", "amount" => "numeric|min:0", "percentage" => "numeric|min:0|max:100"]);
        SalaryComponent::create(array_merge($data, ["is_active" => true]));
        return back()->with("success","Component created.");
    }
    public function updateComponent(Request $request, SalaryComponent $component) {
        $component->update($request->validate(["name" => "required|string|max:100", "is_active" => "boolean", "amount" => "numeric|min:0", "percentage" => "numeric|min:0|max:100"]));
        return back()->with("success","Updated.");
    }
}
