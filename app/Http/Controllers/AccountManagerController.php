<?php
namespace App\Http\Controllers;

use App\Models\{Client, Employee, LeaveRequest, AttendanceLog, EmployeeDocument};
use Illuminate\Http\Request;

class AccountManagerController extends Controller
{
    /**
     * Get the client(s) managed by the current account manager.
     * Super-admin / hr-admin see all clients.
     */
    private function managedClients()
    {
        if (auth()->user()->hasAnyRole(["super-admin","hr-admin"])) {
            return Client::with("employees")->get();
        }
        return Client::where("account_manager_id", auth()->id())->with("employees")->get();
    }

    private function managedEmployeeIds(): array
    {
        return $this->managedClients()->flatMap(fn($c) => $c->employees->pluck("id"))->unique()->values()->toArray();
    }

    public function dashboard()
    {
        $clients   = $this->managedClients();
        $empIds    = $this->managedEmployeeIds();
        $totalEmp  = count($empIds);
        $onLeave   = Employee::whereIn("id", $empIds)->where("status","on_leave")->count();
        $pendingLeaves = LeaveRequest::whereIn("employee_id", $empIds)->where("status","pending")->count();
        $todayPresent  = AttendanceLog::whereIn("employee_id", $empIds)->whereDate("date", today())->where("status","present")->count();
        return view("account-manager.dashboard", compact("clients","totalEmp","onLeave","pendingLeaves","todayPresent"));
    }

    public function employees(Request $request)
    {
        $empIds = $this->managedEmployeeIds();
        $employees = Employee::with(["department","designation","user"])
            ->whereIn("id", $empIds)
            ->when($request->search, fn($q) => $q->where("first_name","like","%{$request->search}%")->orWhere("last_name","like","%{$request->search}%")->orWhere("emp_number","like","%{$request->search}%"))
            ->when($request->status, fn($q) => $q->where("status", $request->status))
            ->paginate(25);
        $clients = $this->managedClients();
        return view("account-manager.employees", compact("employees", "clients"));
    }

    public function showEmployee(Employee $employee)
    {
        $empIds = $this->managedEmployeeIds();
        abort_unless(in_array($employee->id, $empIds), 403);

        $employee->load(["department","designation","user","documents","leaveRequests.leaveType","attendanceLogs","salary"]);
        $recentAttendance = $employee->attendanceLogs()->orderByDesc("date")->limit(14)->get();
        $leaveBalance     = $employee->leaveBalances()->with("leaveType")->get();
        return view("account-manager.employee-detail", compact("employee","recentAttendance","leaveBalance"));
    }

    public function updateEmployee(Request $request, Employee $employee)
    {
        $empIds = $this->managedEmployeeIds();
        abort_unless(in_array($employee->id, $empIds), 403);

        $request->validate([
            "phone"   => "nullable|string|max:20",
            "address" => "nullable|string|max:500",
            "status"  => "in:active,on_leave,suspended",
        ]);
        $employee->update($request->only("phone","address","city","status","emergency_contact_name","emergency_contact_phone","next_of_kin_name","next_of_kin_relation","next_of_kin_phone","next_of_kin_email"));
        return back()->with("success", "Employee updated.");
    }

    public function leaves(Request $request)
    {
        $empIds = $this->managedEmployeeIds();
        $leaves = LeaveRequest::with(["employee.department","leaveType"])
            ->whereIn("employee_id", $empIds)
            ->when($request->status, fn($q) => $q->where("status", $request->status))
            ->orderByDesc("created_at")
            ->paginate(25);
        return view("account-manager.leaves", compact("leaves"));
    }

    public function approveLeave(LeaveRequest $leave)
    {
        abort_unless(in_array($leave->employee_id, $this->managedEmployeeIds()), 403);
        $leave->update(["status" => "approved", "approved_by" => auth()->id(), "approved_at" => now()]);
        $leave->employee->update(["status" => "on_leave"]);
        return back()->with("success", "Leave approved.");
    }

    public function rejectLeave(Request $request, LeaveRequest $leave)
    {
        abort_unless(in_array($leave->employee_id, $this->managedEmployeeIds()), 403);
        $leave->update(["status" => "rejected", "rejection_reason" => $request->reason, "approved_by" => auth()->id(), "approved_at" => now()]);
        return back()->with("success", "Leave rejected.");
    }
}
