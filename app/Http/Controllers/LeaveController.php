<?php
namespace App\Http\Controllers;

use App\Mail\LeaveSubmittedMail;
use App\Mail\LeaveClientNotificationMail;
use App\Mail\LeaveApprovedByClientMail;
use App\Mail\LeaveStatusMail;
use App\Services\NotificationService;
use App\Models\{LeaveRequest, LeaveType, LeaveBalance, Employee, Department, User, Client};
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
            'replacement_name' => 'required|string|max:255',
            'replacement_email'=> 'required|email|max:255',
            'replacement_phone'=> 'required|string|max:30',
        ]);

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
            'replacement_name'  => $request->replacement_name,
            'replacement_email' => $request->replacement_email,
            'replacement_phone' => $request->replacement_phone,
        ]);

        // In-app notification
        app(NotificationService::class)->leaveSubmitted($leave);

        // Email 1: Account Manager
        $this->notifyAccountManager($leave);

        // Email 2: Client company contact
        $this->notifyClient($leave);

        return redirect()->route('leaves.index')->with('success', 'Leave request submitted. Your Account Manager and Client have been notified.');
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
        $leave->load(['employee.user', 'employee.clients', 'employee.department', 'employee.designation', 'leaveType', 'approver']);
        $client = $leave->employee?->clients->first();
        return view('leaves.show', compact('leave', 'client'));
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