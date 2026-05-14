<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ProbationApiController extends Controller
{
    public function index(Request $request)
    {
        $statusFilter = $request->get('status');
        $perPage      = min($request->get('per_page', 20), 100);

        $query = Employee::with(['user', 'designation', 'department'])
            ->whereNotNull('hire_date')
            ->where(function ($q) {
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

        $employees = $query->orderBy('hire_date', 'desc')->paginate($perPage);

        $onProbation  = Employee::where('probation_status', 'on_probation')->count();
        $passed       = Employee::where('probation_status', 'passed')->count();
        $dueThisMonth = Employee::where('probation_end_date', '>=', today())
            ->where('probation_end_date', '<=', today()->endOfMonth())
            ->where('probation_status', 'on_probation')
            ->count();
        $overdue = Employee::where('probation_end_date', '<', today())
            ->where(function ($q) {
                $q->where('probation_status', 'on_probation')->orWhereNull('probation_status');
            })
            ->where('hire_date', '>=', now()->subMonths(12))
            ->count();

        return response()->json([
            'stats' => compact('onProbation', 'passed', 'dueThisMonth', 'overdue'),
            'data'  => $employees->map(fn($e) => $this->formatEmployee($e)),
            'total'        => $employees->total(),
            'current_page' => $employees->currentPage(),
            'last_page'    => $employees->lastPage(),
        ]);
    }

    public function show(Employee $employee)
    {
        $employee->load(['user', 'designation', 'department', 'probationConfirmedBy']);

        return response()->json($this->formatEmployee($employee, true));
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

        return response()->json([
            'message'            => 'Probation end date set to ' . Carbon::parse($request->probation_end_date)->format('d M Y') . '.',
            'probation_end_date' => $employee->probation_end_date->toDateString(),
            'probation_status'   => 'on_probation',
        ]);
    }

    public function confirm(Request $request, Employee $employee)
    {
        $request->validate([
            'outcome'                => 'required|in:passed,failed,extended',
            'notes'                  => 'nullable|string|max:1000',
            'new_probation_end_date' => 'required_if:outcome,extended|nullable|date',
        ]);

        $updateData = [
            'probation_status'       => $request->outcome,
            'probation_confirmed_at' => now(),
            'probation_confirmed_by' => auth()->id(),
        ];

        if ($request->outcome === 'extended' && $request->new_probation_end_date) {
            $updateData['probation_end_date'] = $request->new_probation_end_date;
        }

        if ($request->notes) {
            $updateData['bio'] = trim(($employee->bio ? $employee->bio . "\n\n" : '') .
                '[Probation ' . ucfirst($request->outcome) . ' — ' . now()->format('d M Y') . ']: ' . $request->notes);
        }

        $employee->update($updateData);

        $messages = [
            'passed'   => 'Probation confirmed as PASSED.',
            'failed'   => 'Probation confirmed as FAILED.',
            'extended' => 'Probation extended.',
        ];

        return response()->json([
            'message'          => $messages[$request->outcome],
            'probation_status' => $request->outcome,
        ]);
    }

    private function formatEmployee(Employee $e, bool $full = false): array
    {
        $base = [
            'id'                    => $e->id,
            'emp_number'            => $e->emp_number,
            'full_name'             => $e->full_name,
            'email'                 => $e->email,
            'avatar_url'            => $e->user?->avatar_url ?? null,
            'department'            => $e->department?->name,
            'designation'           => $e->designation?->name,
            'hire_date'             => $e->hire_date?->toDateString(),
            'probation_end_date'    => $e->probation_end_date?->toDateString(),
            'probation_status'      => $e->probation_status,
            'probation_confirmed_at'=> $e->probation_confirmed_at?->toIso8601String(),
            'days_left'             => $e->probation_days_left,
            'is_on_probation'       => $e->is_on_probation,
        ];

        if ($full) {
            $base['probation_confirmed_by'] = $e->probationConfirmedBy?->name;
            $base['bio']                    = $e->bio;
        }

        return $base;
    }
}
