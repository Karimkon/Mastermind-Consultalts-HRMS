<?php
namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\AttendanceLog;
use App\Models\LeaveRequest;
use App\Models\Payslip;
use App\Models\PerformanceReview;
use App\Models\Department;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index()    { return view("reports.index"); }
    public function employees(Request $request) {
        $query = Employee::with(["department","designation"])->where("status","active");
        if ($request->filled("department_id")) $query->where("department_id",$request->department_id);
        $employees   = $query->orderBy("first_name")->get();
        $departments = Department::where("is_active",true)->get();
        return view("reports.employees", compact("employees","departments"));
    }
    public function attendance(Request $request) {
        $month  = $request->get("month", Carbon::today()->format("Y-m"));
        $logs   = AttendanceLog::with("employee.department")->where("date","like","$month%")->orderBy("date")->get();
        return view("reports.attendance", compact("logs","month"));
    }
    public function leave(Request $request) {
        $year  = $request->get("year", date("Y"));
        $items = LeaveRequest::with(["employee","leaveType"])->whereYear("from_date",$year)->where("status","approved")->get();
        return view("reports.leave", compact("items","year"));
    }
    public function payroll(Request $request) {
        $month = $request->get("month", Carbon::today()->format("Y-m"));
        [$y,$m] = explode("-", $month);
        $payslips = Payslip::with("employee.department")->whereHas("payrollRun",fn($q)=>$q->where("year",$y)->where("month",(int)$m))->get();
        return view("reports.payroll", compact("payslips","month"));
    }
    public function performance(Request $request) {
        $year    = $request->get("year", date("Y"));
        $reviews = PerformanceReview::with(["employee.department","cycle"])->whereHas("cycle",fn($q)=>$q->where("year",$year))->get();
        return view("reports.performance", compact("reviews","year"));
    }
    public function export(Request $request, string $type)
    {
        // Returns CSV for now
        $headers = ["Content-Type" => "text/csv", "Content-Disposition" => "attachment;filename={$type}_report.csv"];
        $callback = function () use ($type) {
            $handle = fopen("php://output","w");
            if ($type === "employees") {
                fputcsv($handle, ["ID","Emp Number","Full Name","Department","Designation","Status","Hire Date"]);
                Employee::with(["department","designation"])->chunk(100, function ($employees) use ($handle) {
                    foreach ($employees as $e) fputcsv($handle, [$e->id,$e->emp_number,$e->full_name,$e->department?->name,$e->designation?->title,$e->status,$e->hire_date]);
                });
            }
            fclose($handle);
        };
        return response()->stream($callback, 200, $headers);
    }
}
