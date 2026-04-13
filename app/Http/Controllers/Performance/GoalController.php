<?php
namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\{Employee, EmployeeGoal, PerformanceCycle};
use Illuminate\Http\Request;

class GoalController extends Controller
{
    public function index(Request $request)
    {
        $user     = auth()->user();
        $employee = $user->employee;
        $isAdmin  = $user->hasAnyRole(['super-admin','hr-admin','manager']);

        $goals = EmployeeGoal::with(['employee.user','cycle'])
            ->when(!$isAdmin && $employee, fn($q) => $q->where('employee_id', $employee->id))
            ->when($request->employee_id, fn($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->cycle_id, fn($q) => $q->where('cycle_id', $request->cycle_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')->paginate(25);

        $cycles    = PerformanceCycle::orderByDesc('year')->get();
        $employees = $isAdmin ? Employee::with('user')->where('status','active')->get() : collect();
        return view('performance.goals.index', compact('goals','cycles','employees'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'employee_id' => 'required|exists:employees,id',
            'weight'      => 'nullable|numeric|min:0|max:100',
            'target_date' => 'nullable|date',
        ]);

        EmployeeGoal::create([
            'employee_id' => $request->employee_id,
            'cycle_id'    => $request->cycle_id,
            'title'       => $request->title,
            'description' => $request->description,
            'target_date' => $request->target_date,
            'weight'      => $request->weight ?? 0,
            'status'      => 'not_started',
            'progress'    => 0,
            'created_by'  => auth()->id(),
        ]);

        return back()->with('success', 'Goal created.');
    }

    public function update(Request $request, EmployeeGoal $goal)
    {
        $goal->update($request->only(['title','description','target_date','weight','status','cycle_id']));
        return back()->with('success', 'Goal updated.');
    }

    public function updateProgress(Request $request, EmployeeGoal $goal)
    {
        $request->validate(['progress' => 'required|integer|min:0|max:100']);
        $progress = (int) $request->progress;
        $status   = $goal->status;

        if ($progress === 100) $status = 'achieved';
        elseif ($progress > 0) $status = 'in_progress';

        $goal->update(['progress' => $progress, 'status' => $status]);
        return back()->with('success', 'Progress updated.');
    }

    public function destroy(EmployeeGoal $goal)
    {
        $goal->delete();
        return back()->with('success', 'Goal deleted.');
    }
}
