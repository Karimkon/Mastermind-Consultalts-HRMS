<?php
namespace App\Http\Controllers;

use App\Mail\LeaveSubmittedMail;
use App\Models\{LeaveRequest, LeaveType, LeaveBalance, Employee, Department, User};
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
        $request->validate(['leave_type_id'=>'required','from_date'=>'required|date','to_date'=>'required|date|after_or_equal:from_date','reason'=>'required|string']);
        $days = Carbon::parse($request->from_date)->diffInWeekdays(Carbon::parse($request->to_date)) + 1;
        $leave = LeaveRequest::create(['employee_id' => auth()->user()->employee->id, 'leave_type_id' => $request->leave_type_id, 'from_date' => $request->from_date, 'to_date' => $request->to_date, 'days_count' => $days, 'reason' => $request->reason, 'status' => 'pending']);

        // Notify HR admins and managers
        User::role(['hr-admin','super-admin','manager'])->each(function (User $u) use ($leave) {
            if ($u->email) {
                Mail::to($u->email)->queue(new LeaveSubmittedMail($leave->load(['employee.department','leaveType'])));
            }
        });

        return redirect()->route('leaves.index')->with('success', 'Leave request submitted.');
    }

    public function show(LeaveRequest $leave) { return view('leaves.show', ['leave' => $leave->load(['employee', 'leaveType'])]); }

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
        $leave->update(['status' => 'approved', 'approved_by' => auth()->user()->employee?->id]);
        LeaveBalance::where('employee_id', $leave->employee_id)->where('leave_type_id', $leave->leave_type_id)->where('year', now()->year)->increment('used_days', $leave->days_count);
        $leave->employee->update(['status' => 'on_leave']);
        return back()->with('success', 'Leave approved.');
    }

    public function reject(Request $request, LeaveRequest $leave)
    {
        $leave->update([
            'status'           => 'rejected',
            'approved_by'      => auth()->user()->employee?->id,
            'rejection_reason' => $request->rejection_reason,
        ]);
        return back()->with('success', 'Leave rejected.');
    }

    public function cancel(LeaveRequest $leave)
    {
        $leave->update(['status' => 'cancelled']);
        return back()->with('success', 'Leave cancelled.');
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