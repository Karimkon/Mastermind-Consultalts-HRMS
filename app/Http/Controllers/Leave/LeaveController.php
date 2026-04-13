<?php
namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\LeaveBalance;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $user  = auth()->user();
        $query = LeaveRequest::with(["employee.department", "leaveType"]);

        if (!$user->hasRole(["super-admin","hr-admin","manager"])) {
            $emp = Employee::where("user_id", $user->id)->first();
            if ($emp) $query->where("employee_id", $emp->id);
        }
        if ($request->filled("status"))      $query->where("status", $request->status);
        if ($request->filled("leave_type_id")) $query->where("leave_type_id", $request->leave_type_id);
        if ($request->filled("department_id")) $query->whereHas("employee", fn($q) => $q->where("department_id", $request->department_id));

        if ($request->ajax()) return response()->json($query->get());

        $requests   = $query->orderByDesc("created_at")->paginate(25);
        $leaveTypes = LeaveType::where("is_active",true)->get();
        $departments = \App\Models\Department::where("is_active",true)->get();
        return view("leaves.index", compact("requests","leaveTypes","departments"));
    }

    public function create()
    {
        $leaveTypes = LeaveType::where("is_active",true)->get();
        $employee   = Employee::where("user_id", auth()->id())->with("leaveBalances.leaveType")->first();
        return view("leaves.create", compact("leaveTypes","employee"));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            "leave_type_id" => "required|exists:leave_types,id",
            "from_date"     => "required|date|after_or_equal:today",
            "to_date"       => "required|date|after_or_equal:from_date",
            "reason"        => "required|string|max:500",
        ]);

        $employee = Employee::where("user_id", auth()->id())->firstOrFail();
        $days     = Carbon::parse($data["from_date"])->diffInWeekdays(Carbon::parse($data["to_date"])->addDay());

        LeaveRequest::create(array_merge($data, [
            "employee_id" => $employee->id,
            "days_count"  => $days,
            "status"      => "pending",
        ]));

        // Notify HR
        if ($employee->manager_id) {
            $manager = Employee::find($employee->manager_id);
            if ($manager?->user_id) {
                Notification::create(["user_id" => $manager->user_id, "type" => "leave_request", "title" => "New Leave Request", "body" => "{$employee->full_name} requested {$days} day(s) leave.", "action_url" => "/leave"]);
            }
        }

        return redirect()->route("leave.index")->with("success", "Leave request submitted.");
    }

    public function show(LeaveRequest $leave) {
        $leave->load(["employee", "leaveType", "approver"]);
        return view("leaves.show", compact("leave"));
    }

    public function edit(LeaveRequest $leave) {
        if ($leave->status !== "pending") return back()->withErrors(["error" => "Cannot edit a processed leave."]);
        $leaveTypes = LeaveType::where("is_active",true)->get();
        return view("leaves.edit", compact("leave","leaveTypes"));
    }

    public function update(Request $request, LeaveRequest $leave)
    {
        if ($leave->status !== "pending") return back()->withErrors(["error" => "Cannot modify a processed leave."]);
        $data = $request->validate(["from_date" => "required|date", "to_date" => "required|date|after_or_equal:from_date", "reason" => "required|string|max:500"]);
        $days = Carbon::parse($data["from_date"])->diffInWeekdays(Carbon::parse($data["to_date"])->addDay());
        $leave->update(array_merge($data, ["days_count" => $days]));
        return redirect()->route("leave.index")->with("success", "Leave updated.");
    }

    public function destroy(LeaveRequest $leave)
    {
        $leave->delete();
        return redirect()->route("leave.index")->with("success", "Leave deleted.");
    }

    public function approve(Request $request, LeaveRequest $leave)
    {
        if ($leave->status !== "pending") return back()->withErrors(["error" => "Already processed."]);

        $leave->update(["status" => "approved", "approved_by" => auth()->id(), "actioned_at" => now()]);

        // Update balance
        LeaveBalance::where("employee_id", $leave->employee_id)
            ->where("leave_type_id", $leave->leave_type_id)
            ->where("year", Carbon::parse($leave->from_date)->year)
            ->increment("used_days", $leave->days_count);
        LeaveBalance::where("employee_id", $leave->employee_id)
            ->where("leave_type_id", $leave->leave_type_id)
            ->where("year", Carbon::parse($leave->from_date)->year)
            ->decrement("pending_days", $leave->days_count);

        // Notify employee
        if ($leave->employee->user_id) {
            Notification::create(["user_id" => $leave->employee->user_id, "type" => "leave_approved", "title" => "Leave Approved", "body" => "Your leave request has been approved.", "action_url" => "/leave"]);
        }

        return back()->with("success", "Leave approved.");
    }

    public function reject(Request $request, LeaveRequest $leave)
    {
        $request->validate(["rejection_reason" => "required|string|max:500"]);
        $leave->update(["status" => "rejected", "approved_by" => auth()->id(), "rejection_reason" => $request->rejection_reason, "actioned_at" => now()]);

        if ($leave->employee->user_id) {
            Notification::create(["user_id" => $leave->employee->user_id, "type" => "leave_rejected", "title" => "Leave Rejected", "body" => "Your leave request was rejected. Reason: {$request->rejection_reason}", "action_url" => "/leave"]);
        }

        return back()->with("success", "Leave rejected.");
    }

    public function cancel(Request $request, LeaveRequest $leave)
    {
        if (!in_array($leave->status, ["pending","approved"])) return back()->withErrors(["error" => "Cannot cancel."]);
        $leave->update(["status" => "cancelled"]);
        return back()->with("success", "Leave cancelled.");
    }

    public function types()
    {
        $types = LeaveType::orderBy("name")->get();
        return view("leaves.types", compact("types"));
    }

    public function storeType(Request $request)
    {
        $data = $request->validate(["name" => "required|string|max:100", "code" => "required|string|max:20|unique:leave_types", "days_allowed" => "required|integer|min:0", "is_paid" => "boolean", "carry_forward" => "boolean", "color" => "nullable|string|max:20"]);
        LeaveType::create(array_merge($data, ["is_active" => true]));
        return back()->with("success", "Leave type created.");
    }

    public function updateType(Request $request, LeaveType $type)
    {
        $data = $request->validate(["name" => "required|string|max:100", "days_allowed" => "required|integer|min:0", "is_paid" => "boolean", "carry_forward" => "boolean", "is_active" => "boolean", "color" => "nullable|string|max:20"]);
        $type->update($data);
        return back()->with("success", "Leave type updated.");
    }

    public function balance($employeeId = null)
    {
        $employee = $employeeId ? Employee::findOrFail($employeeId) : Employee::where("user_id",auth()->id())->firstOrFail();
        $balances = LeaveBalance::with("leaveType")->where("employee_id",$employee->id)->where("year",date("Y"))->get();
        return view("leaves.balance", compact("employee","balances"));
    }
}
