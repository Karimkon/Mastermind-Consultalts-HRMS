<?php
namespace App\Http\Controllers;

use App\Models\{Employee, Notification};
use App\Mail\ProbationOutcomeMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
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

        // The reason used to be appended to `bio`, which is printed on the
        // employee's profile page - so the grounds for failing someone were on
        // show to anyone who could open it. It lives in its own column now.
        if ($request->notes) {
            $updateData['probation_notes'] = $request->notes;
        }

        $employee->update($updateData);
        $employee->refresh();

        $told = $this->tellEmployee($employee, $request->outcome, $request->notes);

        $outcomeMessages = [
            'passed'   => 'Probation confirmed as PASSED.',
            'failed'   => 'Probation confirmed as FAILED.',
            'extended' => 'Probation extended.',
        ];

        return redirect()->route('probation.show', $employee)
            ->with('success', $outcomeMessages[$request->outcome] . ' ' . $told);
    }

    /**
     * Tell the employee what was decided, in the app and by email.
     *
     * Returns a sentence for the confirmation banner, because "the decision is
     * saved" and "the person has been told" are different facts and HR needs to
     * know which one happened. A failure here never unwinds the decision: the
     * outcome is recorded, and the reason the letter did not go is logged.
     */
    private function tellEmployee(Employee $employee, string $outcome, ?string $notes): string
    {
        if (! $employee->user_id) {
            return $employee->full_name . ' has no system account, so no message was sent.';
        }

        $title = match ($outcome) {
            'passed'   => 'Congratulations - your employment is confirmed',
            'extended' => 'Your probation has been extended',
            'failed'   => 'Outcome of your probation review',
            default    => 'Probation review outcome',
        };

        $body = match ($outcome) {
            'passed'   => 'Your probation period is complete and your employment has been '
                        . 'confirmed. Thank you for the work you have put in so far.',
            'extended' => 'Your probation has been extended to '
                        . ($employee->probation_end_date?->format('d M Y') ?? 'a new date')
                        . '. Your manager will go through what is expected before then.',
            'failed'   => 'A decision has been taken not to confirm your employment at the end '
                        . 'of your probation period. HR will be in touch to discuss this.',
            default    => 'Your probation review has been completed.',
        };

        Notification::create([
            'user_id' => $employee->user_id,
            'type'    => 'probation_' . $outcome,
            'title'   => $title,
            'body'    => $body,
            'data'    => ['employee_id' => $employee->id, 'url' => route('profile')],
        ]);

        $email = $employee->user?->email;
        if (! $email) {
            return 'Posted in the app; there is no email address on file.';
        }

        try {
            Mail::to($email)->send(new ProbationOutcomeMail($employee, $outcome, $notes));
            return $employee->first_name . ' has been notified in the app and by email.';
        } catch (\Throwable $e) {
            Log::warning('Probation outcome email failed', [
                'employee_id' => $employee->id,
                'reason'      => $e->getMessage(),
            ]);
            return 'Posted in the app, but the email could not be sent.';
        }
    }
}
