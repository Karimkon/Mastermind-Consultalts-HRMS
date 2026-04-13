<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Department, Designation, Employee};
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments  = Department::with(['head','parent'])->withCount('employees')->get();
        $designations = Designation::with('department')->get();
        $employees    = Employee::with('user')->where('status', 'active')->get();
        return view('admin.departments.index', compact('departments', 'designations', 'employees'));
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:100']);
        Department::create($request->only('name', 'parent_id', 'head_id'));
        return back()->with('success', 'Department created.');
    }

    public function update(Request $request, Department $department)
    {
        $request->validate(['name' => 'required|string|max:100']);
        $department->update($request->only('name', 'parent_id', 'head_id'));
        return back()->with('success', 'Department updated.');
    }

    public function destroy(Department $department)
    {
        $department->delete();
        return back()->with('success', 'Department deleted.');
    }

    public function storeDesignation(Request $request)
    {
        $request->validate(['title' => 'required|string|max:100']);
        Designation::create($request->only('title', 'department_id', 'grade'));
        return back()->with('success', 'Designation created.');
    }

    public function destroyDesignation(Designation $designation)
    {
        $designation->delete();
        return back()->with('success', 'Designation deleted.');
    }
}