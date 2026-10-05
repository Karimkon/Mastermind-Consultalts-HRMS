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
        $data = $request->validate(['name' => 'required|string|max:100']);

        // One of these already existing is not an error worth a red screen:
        // two people adding "Operations" minutes apart should both end up
        // pointing at the same department, not at two of them.
        $department = Department::firstOrCreate(
            ['name' => $data['name']],
            $request->only('parent_id', 'head_id')
        );

        // Answering JSON lets the employee form add one without navigating
        // away; the Departments screen still posts a form and wants the
        // redirect it has always had.
        if ($request->expectsJson()) {
            return response()->json([
                'id'      => $department->id,
                'name'    => $department->name,
                'created' => $department->wasRecentlyCreated,
            ], $department->wasRecentlyCreated ? 201 : 200);
        }

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
        $data = $request->validate(['title' => 'required|string|max:100']);

        $designation = Designation::firstOrCreate(
            ['title' => $data['title']],
            $request->only('department_id', 'grade')
        );

        if ($request->expectsJson()) {
            return response()->json([
                'id'      => $designation->id,
                'title'   => $designation->title,
                'created' => $designation->wasRecentlyCreated,
            ], $designation->wasRecentlyCreated ? 201 : 200);
        }

        return back()->with('success', 'Designation created.');
    }

    public function destroyDesignation(Designation $designation)
    {
        $designation->delete();
        return back()->with('success', 'Designation deleted.');
    }
}