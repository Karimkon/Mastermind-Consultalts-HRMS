<?php
namespace App\Http\Controllers;

use App\Mail\LeaveSubmittedMail;
use App\Mail\LeaveClientNotificationMail;
use App\Mail\LeaveApprovedByClientMail;
use App\Mail\LeaveStatusMail;
use App\Services\LeaveAdjustmentService;
use App\Services\NotificationService;
use App\Models\{LeaveRequest, LeaveType, LeaveBalance, Employee, Department, User, Client, Notification};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $user     = auth()->user();
        $employee = $user->employee;
        $isAdmin  = $user->hasAnyRole(['super-admin','hr-admin','manager']);

        $query = LeaveRequest::with(['employee.department', 'leaveType'])
            ->when(!$isAdmin && $employee, fn($q) => $q->where('employee_id', $employee->id))
            ->when($isAdmin && $request->employee_id, fn($q) => $q->where('employee_id', $request->employee_id))
            ->when($isAdmin && $request->department_id, fn($q) => $q->whereHas('employee', fn($e) => $e->where('department_id', $request->department_id)))
            ->when($request->leave_type_id, fn($q) => $q->where('leave_type_id', $request->leave_type_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->year, fn($q) => $q->whereYear('from_date', $request->year))
            ->orderByDesc('created_at');

        $requests   = $query->paginate(25);
        $leaveTypes = LeaveType::all();
        $departments = Department::orderBy('name')->get();
        return view('leaves.index', compact('requests', 'leaveTypes', 'departments'));
    }

    public function create()
    {
        $leaveTypes = LeaveType::all();
        $employee   = auth()->user()->employee;
        $balances   = $employee ? LeaveBalance::where('employee_id', $employee->id)->where('year', now()->year)->with('leaveType')->get() : collect();
        return view('leaves.create', compact('leaveTypes', 'balances', 'employee'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'leave_type_id'    => 'required|exists:leave_types,id',
            'from_date'        => 'required|date',
            'to_date'          => 'required|date|after_or_equal:from_date',
            'reason'           => 'required|string',
            // Chosen from the staff register rather than typed. A free-typed name
            // could not be notified, could not see the request, and quietly
            // disagreed with Employee Central about spelling.
            'replacement_employee_id' => 'required|exists:employees,id',
            'replacement_email'=> 'nullable|email|max:255',
            'replacement_phone'=> 'nullable|string|max:30',
        ], [
            'replacement_employee_id.required' => 'Choose the person who will cover for you.',
        ]);

        $cover = Employee::with('user')->findOrFail($request->replacement_employee_id);
        $mine  = auth()->user()->employee;

        if ($mine && $cover->id === $mine->id) {
            return back()->withInput()->withErrors([
                'replacement_employee_id' => 'You cannot nominate yourself to cover your own leave.',
            ]);
        }

        // Their own record is the source; the form may override it for a
        // one-off. Said plainly rather than failing silently at send time.
        $coverEmail = $request->replacement_email ?: ($cover->user?->email ?? $cover->personal_email);
        $coverPhone = $request->replacement_phone ?: $cover->phone;

        if (! $coverEmail) {
            return back()->withInput()->withErrors([
                'replacement_email' => "{$cover->full_name} has no email address on record, so they cannot be notified. "
                    . 'Add one in Employee Central, or type an address here.',
            ]);
        }

        $days  = Carbon::parse($request->from_date)->diffInWeekdays(Carbon::parse($request->to_date)) + 1;
        $empId = auth()->user()->employee->id;

        $leave = LeaveRequest::create([
            'employee_id'       => $empId,
            'leave_type_id'     => $request->leave_type_id,
            'from_date'         => $request->from_date,
            'to_date'           => $request->to_date,
            'days_count'        => $days,
            'reason'            => $request->reason,
            'status'            => 'pending',
            'replacement_employee_id' => $cover->id,
            // Snapshotted as well as linked: a request from two years ago should
            // still read correctly after this person changes their number or
            // leaves, the same reason a payslip keeps its own figures.
            'replacement_name'  => $cover->full_name,
            'replacement_email' => $coverEmail,
            'replacement_phone' => $coverPhone,
        ]);

        // In-app notification
        app(NotificationService::class)->leaveSubmitted($leave);

        // Email 1: Account Manager
        $this->notifyAccountManager($leave);

        // Email 2: Client company contact
        $this->notifyClient($leave);

        // Email 3: the person being asked to cover
        $this->notifyCoverPerson($leave, $cover);

        return redirect()->route('leaves.index')->with('success',
            "Leave request submitted. {$cover->full_name} has been told they are nominated to cover, "
            . 'and your Account Manager and Client have been notified.');
    }

    /**
     * The cover person hears about it when they are nominated, not when the
     * request is finally approved days later.
     *
     * Worded as a nomination, because that is what it is at this point: saying
     * "you are covering" before the client has approved would be telling them
     * something that is not yet true.
     */
    private function notifyCoverPerson(LeaveRequest $leave, Employee $cover): void
    {
        try {
            if ($cover->user) {
                Notification::create([
                    'user_id' => $cover->user->id,
                    'type'    => 'leave_cover_nominated',
                    'title'   => 'You have been nominated to cover',
                    'body'    => sprintf(
                        '%s has asked you to cover their responsibilities from %s to %s (%s day(s)), pending approval.',
                        $leave->employee?->full_name ?? 'A colleague',
                        $leave->from_date->format('d M Y'),
                        $leave->to_date->format('d M Y'),
                        $leave->days_count
                    ),
                    'data'    => ['leave_id' => $leave->id],
                ]);
            }

            // Sent, not queued: the queue has no worker of its own on this host,
            // and a nomination that arrives after the leave has started is no use.
            if ($leave->replacement_email) {
                Mail::to($leave->replacement_email)->send(new \App\Mail\LeaveCoverNominatedMail($leave));
            }
        } catch (\Exception $e) {
            // A failed notification must not lose the leave request.
            logger()->error('Leave cover nomination failed: ' . $e->getMessage());
        }
    }

    private function notifyAccountManager(LeaveRequest $leave): void
    {
        try {
            // Find the client this employee belongs to
            $client = Client::whereHas('employees', fn($q) => $q->where('employees.id', $leave->employee_id))->first();
            if (!$client) return;

            // Get the account manager user
            $amUser = $client->accountManager;
            if ($amUser && $amUser->email) {
                Mail::to($amUser->email)->send(new LeaveSubmittedMail($leave));
            }
        } catch (\Exception $e) {
            // Don't fail the request if mail fails
            logger()->error('AM leave email failed: ' . $e->getMessage());
        }
    }

    private function notifyClient(LeaveRequest $leave): void
    {
        try {
            $client = Client::whereHas('employees', fn($q) => $q->where('employees.id', $leave->employee_id))->first();
            if (!$client) return;

            // Client contact (the user account linked to the client, or contact_person if no user)
            $clientUser = $client->user;
            $clientEmail = $clientUser?->email ?? null;

            if ($clientEmail) {
                Mail::to($clientEmail)->send(new LeaveClientNotificationMail($leave));
            }
        } catch (\Exception $e) {
            logger()->error('Client leave email failed: ' . $e->getMessage());
        }
    }

    public function show(LeaveRequest $leave)
    {
        $leave->load(['employee.user', 'employee.clients', 'employee.department',
                      'employee.designation', 'leaveType', 'approver', 'recaller']);

        $client = $leave->employee?->clients->first();

        // What is left of this person's entitlement, so a decision to extend is
        // made against the balance rather than in the dark.
        $balance = $leave->employee
            ? app(LeaveAdjustmentService::class)->balanceFor($leave->employee, $leave->leave_type_id)
            : null;

        $canManage = auth()->user()->hasAnyRole(['super-admin', 'hr-admin', 'manager']);

        return view('leaves.show', compact('leave', 'client', 'balance', 'canManage'));
    }

    /**
     * Bring somebody back from leave early.
     *
     * The leave ends today and the days from tomorrow are given back. A leave
     * that has not started yet is cancelled outright instead, because one
     * ending before it begins is not a shortened leave.
     */
    public function recall(Request $request, LeaveRequest $leave)
    {
        $this->authoriseManage();

        if ($leave->status !== 'approved') {
            return back()->with('error', 'Only an approved leave can be recalled.');
        }

        $data = $request->validate(['note' => 'nullable|string|max:500']);

        $result = app(LeaveAdjustmentService::class)->recall($leave, $data['note'] ?? null);

        app(NotificationService::class);

        $this->tellEmployee($leave,
            'You have been recalled from leave',
            $result['cancelled']
                ? 'Your approved leave was cancelled before it started. '
                  . $result['returned'] . ' day(s) have gone back to your balance.'
                : 'Your leave now ends today. ' . $result['returned']
                  . ' day(s) have gone back to your balance.');

        return back()->with('success', $result['cancelled']
            ? "Leave cancelled and {$result['returned']} day(s) returned to the balance."
            : "Recalled. The leave now ends today ({$result['days']} day(s) taken) and "
              . "{$result['returned']} day(s) went back to the balance.");
    }

    /**
     * Lengthen or shorten a leave that has already been granted.
     *
     * The new end date is given; the days are recounted the same way the
     * application form counts them, and the balance moves by the difference.
     */
    public function adjust(Request $request, LeaveRequest $leave)
    {
        $this->authoriseManage();

        if (! in_array($leave->status, ['approved', 'pending'], true)) {
            return back()->with('error', 'Only a pending or approved leave can be changed.');
        }

        $data = $request->validate([
            'to_date' => 'required|date|after_or_equal:'.$leave->from_date->toDateString(),
            'note'    => 'nullable|string|max:500',
        ], [
            'to_date.after_or_equal' => 'The leave cannot end before it starts.',
        ]);

        $service = app(LeaveAdjustmentService::class);
        $was = (float) $leave->days_count;

        // Refusing here rather than letting the balance go negative: a person
        // cannot take more days than they have.
        $balance = $leave->employee
            ? $service->balanceFor($leave->employee, $leave->leave_type_id)
            : null;

        $wouldBe = $service->workingDays($leave->from_date, \Carbon\Carbon::parse($data['to_date']));
        $extra = $wouldBe - $was;

        if ($balance && $extra > 0 && $extra > $balance['remaining']) {
            return back()->with('error', sprintf(
                'That would add %s day(s) but only %s remain of the %s entitlement.',
                round($extra, 2), round($balance['remaining'], 2), $leave->leaveType?->name ?? 'leave'
            ));
        }

        $now = $service->changeEndDate($leave, \Carbon\Carbon::parse($data['to_date']), $data['note'] ?? null);

        $this->tellEmployee($leave,
            $now > $was ? 'Your leave has been extended' : 'Your leave has been shortened',
            'It now runs to '.$leave->fresh()->to_date->format('d M Y')." ({$now} day(s)).");

        return back()->with('success', sprintf(
            '%s from %s to %s day(s).',
            $now > $was ? 'Extended' : ($now < $was ? 'Shortened' : 'Unchanged'),
            round($was, 2), round($now, 2)
        ));
    }

    private function authoriseManage(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['super-admin', 'hr-admin', 'manager']), 403,
            'Only HR, a manager or an administrator can change a granted leave.');
    }

    private function tellEmployee(LeaveRequest $leave, string $title, string $body): void
    {
        if (! $leave->employee?->user_id) {
            return;
        }

        Notification::create([
            'user_id' => $leave->employee->user_id,
            'type'    => 'leave_adjusted',
            'title'   => $title,
            'body'    => $body,
            'data'    => ['leave_id' => $leave->id],
        ]);
    }

    public function edit(LeaveRequest $leave)
    {
        $leaveTypes = LeaveType::all();
        return view('leaves.edit', compact('leave', 'leaveTypes'));
    }

    public function update(Request $request, LeaveRequest $leave)
    {
        if ($leave->status !== 'pending') return back()->with('error', 'Cannot edit approved/rejected leave.');
        $request->validate(['leave_type_id'=>'required','from_date'=>'required|date','to_date'=>'required|date|after_or_equal:from_date']);
        $days = Carbon::parse($request->from_date)->diffInWeekdays(Carbon::parse($request->to_date)) + 1;
        $leave->update(['leave_type_id'=>$request->leave_type_id,'from_date'=>$request->from_date,'to_date'=>$request->to_date,'days_count'=>$days,'reason'=>$request->reason]);
        return redirect()->route('leaves.index')->with('success', 'Leave updated.');
    }

    public function destroy(LeaveRequest $leave) { $leave->delete(); return back()->with('success', 'Deleted.'); }

    public function approve(LeaveRequest $leave)
    {
        // Check annual limit based on leave type days_allowed
        $leaveType = $leave->leaveType;
        if ($leaveType) {
            $usedThisYear = LeaveBalance::where('employee_id', $leave->employee_id)
                ->where('leave_type_id', $leave->leave_type_id)
                ->where('year', now()->year)
                ->value('used_days') ?? 0;

            $maxAllowed = $leaveType->days_allowed ?? 999;
            if (($usedThisYear + $leave->days_count) > $maxAllowed) {
                return back()->with('error', "Cannot approve: employee has only " . ($maxAllowed - $usedThisYear) . " day(s) remaining for {$leaveType->name} this year (max {$maxAllowed} days).");
            }
        }

        $leave->update(['status' => 'approved', 'approved_by' => auth()->user()->employee?->id]);

        // Deduct from leave balance
        LeaveBalance::where('employee_id', $leave->employee_id)
            ->where('leave_type_id', $leave->leave_type_id)
            ->where('year', now()->year)
            ->increment('used_days', $leave->days_count);

        $leave->employee->update(['status' => 'on_leave']);
        app(NotificationService::class)->leaveStatusChanged($leave->fresh());

        // Email to applicant
        $this->emailApplicant($leave->fresh());

        // Email to replacement person
        $this->emailReplacement($leave->fresh());

        return back()->with('success', 'Leave approved. Employee and replacement person notified by email.');
    }

    private function emailApplicant(LeaveRequest $leave): void
    {
        try {
            $email = $leave->employee?->user?->email ?? $leave->employee?->personal_email;
            if ($email) {
                Mail::to($email)->send(new LeaveStatusMail($leave));
            }
        } catch (\Exception $e) {
            logger()->error('Leave status email to applicant failed: ' . $e->getMessage());
        }
    }

    private function emailReplacement(LeaveRequest $leave): void
    {
        try {
            if ($leave->replacement_email) {
                Mail::to($leave->replacement_email)->send(new \App\Mail\LeaveReplacementMail($leave));
            }
        } catch (\Exception $e) {
            logger()->error('Leave replacement email failed: ' . $e->getMessage());
        }
    }

    public function reject(Request $request, LeaveRequest $leave)
    {
        $wasApproved = $leave->status === 'approved';

        $leave->update([
            'status'           => 'rejected',
            'approved_by'      => auth()->user()->employee?->id,
            'rejection_reason' => $request->rejection_reason,
        ]);

        // Restore leave balance if it was previously approved (balance was already deducted)
        if ($wasApproved) {
            LeaveBalance::where('employee_id', $leave->employee_id)
                ->where('leave_type_id', $leave->leave_type_id)
                ->where('year', $leave->from_date->year)
                ->decrement('used_days', $leave->days_count);
        } else {
            // A request that was still waiting had its days reserved against
            // the balance. Rejecting it released nothing, so the days stayed
            // held for the rest of the year and the person's remaining
            // entitlement read short - James Mugisha was holding 3 days on a
            // request that had been refused.
            LeaveBalance::where('employee_id', $leave->employee_id)
                ->where('leave_type_id', $leave->leave_type_id)
                ->where('year', $leave->from_date->year)
                ->where('pending_days', '>=', $leave->days_count)
                ->decrement('pending_days', $leave->days_count);
        }

        // Reset employee status to active if no other active approved leaves exist
        $this->maybeRestoreEmployeeStatus($leave->employee_id);

        app(NotificationService::class)->leaveStatusChanged($leave->fresh());
        return back()->with('success', 'Leave rejected.');
    }

    public function cancel(LeaveRequest $leave)
    {
        $wasApproved = $leave->status === 'approved';

        $leave->update(['status' => 'cancelled']);

        // Restore leave balance if cancelling an already-approved leave
        if ($wasApproved) {
            LeaveBalance::where('employee_id', $leave->employee_id)
                ->where('leave_type_id', $leave->leave_type_id)
                ->where('year', $leave->from_date->year)
                ->decrement('used_days', $leave->days_count);
        }

        // Reset employee status to active if no other active approved leaves exist
        $this->maybeRestoreEmployeeStatus($leave->employee_id);

        return back()->with('success', 'Leave cancelled.');
    }

    /**
     * Restore employee status to 'active' if they have no other currently active approved leaves.
     */
    private function maybeRestoreEmployeeStatus(int $employeeId): void
    {
        $today = now()->toDateString();
        $hasActiveLeave = LeaveRequest::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->where('from_date', '<=', $today)
            ->where('to_date', '>=', $today)
            ->exists();

        if (!$hasActiveLeave) {
            Employee::where('id', $employeeId)->where('status', 'on_leave')->update(['status' => 'active']);
        }
    }

    public function types()
    {
        $types = LeaveType::paginate(20);
        return view('leaves.types', compact('types'));
    }

    public function storeType(Request $request)
    {
        $request->validate(['name'=>'required','code'=>'required','days_allowed'=>'required|integer']);
        LeaveType::create([
            'name'             => $request->name,
            'code'             => strtoupper($request->code),
            'days_allowed'     => $request->days_allowed,
            'carry_forward'    => $request->boolean('carry_forward'),
            'is_paid'          => $request->boolean('is_paid'),
            'color'            => $request->input('color', '#3b82f6'),
            'is_active'        => true,
        ]);
        return back()->with('success', 'Leave type created.');
    }

    public function balance(Request $request)
    {
        $employees  = Employee::with('user')->where('status','active')->get();
        $leaveTypes = LeaveType::all();
        $balances   = LeaveBalance::with(['employee.user','leaveType'])
            ->when($request->employee_id, fn($q) => $q->where('employee_id', $request->employee_id))
            ->where('year', $request->year ?? now()->year)->paginate(30);
        return view('leaves.balance', compact('employees', 'leaveTypes', 'balances'));
    }
}