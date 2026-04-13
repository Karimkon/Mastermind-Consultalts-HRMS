<?php
namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\{Employee, Pip, PerformanceCycle, Notification};
use Illuminate\Http\Request;

class PipController extends Controller
{
    public function index()
    {
        $pips = Pip::with(['employee.user','cycle'])
            ->orderByDesc('created_at')->paginate(20);
        return view('performance.pip.index', compact('pips'));
    }

    public function create()
    {
        $employees = Employee::with('user')->where('status','active')->get();
        $cycles    = PerformanceCycle::orderByDesc('year')->get();
        return view('performance.pip.create', compact('employees','cycles'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'title'       => 'required|string',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after:start_date',
        ]);

        $objectives = [];
        if ($request->objectives) {
            foreach ($request->objectives as $obj) {
                if (!empty($obj)) $objectives[] = $obj;
            }
        }

        $pip = Pip::create([
            'employee_id' => $request->employee_id,
            'cycle_id'    => $request->cycle_id,
            'title'       => $request->title,
            'description' => $request->description,
            'start_date'  => $request->start_date,
            'end_date'    => $request->end_date,
            'objectives'  => $objectives,
            'status'      => 'active',
            'created_by'  => auth()->id(),
        ]);

        // Notify the employee
        $userId = $pip->employee?->user?->id;
        if ($userId) {
            Notification::create([
                'user_id' => $userId,
                'type'    => 'pip',
                'title'   => 'Performance Improvement Plan Created',
                'body'    => "A PIP titled '{$pip->title}' has been created for you. Please review it.",
            ]);
        }

        return redirect()->route('pips.index')->with('success', 'PIP created and employee notified.');
    }

    public function show(Pip $pip)
    {
        return view('performance.pip.show', ['pip' => $pip->load(['employee.user','cycle'])]);
    }

    public function update(Request $request, Pip $pip)
    {
        $pip->update($request->only(['status','outcome','end_date','description']));
        return back()->with('success', 'PIP updated.');
    }

    public function destroy(Pip $pip)
    {
        $pip->delete();
        return back()->with('success', 'PIP deleted.');
    }
}
