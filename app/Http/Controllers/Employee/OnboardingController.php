<?php
namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\{Employee, OnboardingTask};
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function index(Employee $employee)
    {
        $tasks = $employee->onboardingTasks()->get();
        return view('employees.onboarding', compact('employee', 'tasks'));
    }

    public function store(Request $request, Employee $employee)
    {
        $request->validate(['task' => 'required|string|max:200']);
        OnboardingTask::create([
            'employee_id' => $employee->id,
            'task'        => $request->task,
            'description' => $request->description,
            'sort_order'  => $employee->onboardingTasks()->max('sort_order') + 1,
        ]);
        return back()->with('success', 'Task added.');
    }

    public function complete(OnboardingTask $task)
    {
        $task->update([
            'completed_at' => $task->completed_at ? null : now(),
            'completed_by' => $task->completed_at ? null : auth()->id(),
        ]);
        return back()->with('success', $task->completed_at ? 'Task marked complete.' : 'Task unmarked.');
    }

    public function destroy(OnboardingTask $task)
    {
        $task->delete();
        return back()->with('success', 'Task removed.');
    }
}
