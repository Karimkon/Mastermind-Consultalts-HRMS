<?php
namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ProbationController extends Controller
{
    public function index(Request $request)
    {
        $statusFilter = $request->get('status');

        $query = Employee::with(['user', 'designation', 'department'])
            ->whereNotNull('hire_date');

        // Show employees who are tracked in probation OR hired in last 12 months
        $query->where(function ($q) {
            $q->whereNotNull('probation_status')
              ->orWhere('hire_date', '>=', now()->subMonths(12));
        });

        if ($statusFilter === 'overdue') {
            $query->where('probation_end_date', '<', today())
                  ->where(function ($q) {
                      $q->where('probation_status', 'on_probation')
                        ->orWhereNull('probation_status');
                  });
        } elseif ($statusFilter) {
            $query->where('probation_status', $statusFilter);
        }

        $employees = $query->orderBy('hire_date', 'desc')->paginate(25);

        // Stats
        $onProbation  = Employee::where('probation_status', 'on_probation')->count();
        $passed       = Employee::where('probation_status', 'passed')->count();
        $dueThisMonth = Employee::where('probation_end_date', '>=', today())
                            ->where('probation_end_date', '<=', today()->endOfMonth())
                            ->where('probation_status', 'on_probation')
                            ->count();
        $overdue      = Employee::where('probation_end_date', '<', today())
                            ->where(function ($q) { $q->where('probation_status','on_probation')->orWhereNull('probation_status'); })
                            ->where('hire_date', '>=', now()->subMonths(12))
                            ->count();

        $stats = compact('onProbation', 'passed', 'dueThisMonth', 'overdue');

        return view('probation.index', compact('employees', 'stats', 'statusFilter'));
    }

    public function show(Employee $employee)
    {
        $employee->load(['user', 'designation', 'department', 'probationConfirmedBy']);
        return view('probation.show', compact('employee'));
    }

    public function setProbationEnd(Request $request, Employee $employee)
    {
        $request->validate([
            'probation_end_date' => 'required|date|after:' . $employee->hire_date->format('Y-m-d'),
        ]);

        $employee->update([
            'probation_end_date' => $request->probation_end_date,
            'probation_status'   => 'on_probation',
        ]);

        return redirect()->route('probation.show', $employee)
            ->with('success', "Probation end date set to " . Carbon::parse($request->probation_end_date)->format('d M Y') . ".");
    }

    public function confirm(Request $request, Employee $employee)
    {
        $request->validate([
            'outcome'               => 'required|in:passed,failed,extended',
            'notes'                 => 'nullable|string|max:1000',
            'new_probation_end_date'=> 'required_if:outcome,extended|nullable|date',
        ]);

        $updateData = [
            'probation_status'        => $request->outcome,
            'probation_confirmed_at'  => now(),
            'probation_confirmed_by'  => auth()->id(),
        ];

        if ($request->outcome === 'extended' && $request->new_probation_end_date) {
            $updateData['probation_end_date'] = $request->new_probation_end_date;
        }

        if ($request->notes) {
            $updateData['bio'] = trim(($employee->bio ? $employee->bio . "\n\n" : '') .
                "[Probation " . ucfirst($request->outcome) . " — " . now()->format('d M Y') . "]: " . $request->notes);
        }

        $employee->update($updateData);

        $outcomeMessages = [
            'passed'   => 'Probation confirmed as PASSED.',
            'failed'   => 'Probation confirmed as FAILED.',
            'extended' => 'Probation extended.',
        ];

        return redirect()->route('probation.show', $employee)
            ->with('success', $outcomeMessages[$request->outcome]);
    }
}
