<?php
namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\{Employee, ExitWorkflow};
use Illuminate\Http\Request;

class ExitController extends Controller
{
    public function create(Employee $employee)
    {
        return view('employees.exit', compact('employee'));
    }

    public function store(Request $request, Employee $employee)
    {
        $request->validate([
            'exit_date' => 'required|date',
            'reason'    => 'required|in:resignation,termination,retirement,redundancy,other',
        ]);

        ExitWorkflow::updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'exit_date'    => $request->exit_date,
                'reason'       => $request->reason,
                'status'       => 'initiated',
                'initiated_by' => auth()->id(),
            ]
        );

        $employee->update(['status' => 'terminated', 'end_date' => $request->exit_date]);
        return redirect()->route('employees.show', $employee)->with('success', 'Exit workflow initiated.');
    }

    public function show(Employee $employee)
    {
        $exit = $employee->exitWorkflow;
        if (!$exit) return redirect()->route('employees.exit.create', $employee);
        return view('employees.exit', compact('employee', 'exit'));
    }

    public function update(Request $request, Employee $employee)
    {
        $exit = $employee->exitWorkflow;
        if (!$exit) return back()->with('error', 'No exit workflow found.');

        $exit->update($request->only([
            'interview_notes','equipment_returned','equipment_notes',
            'final_settlement_done','settlement_amount','clearance_done','status',
        ]));

        return back()->with('success', 'Exit workflow updated.');
    }
}
