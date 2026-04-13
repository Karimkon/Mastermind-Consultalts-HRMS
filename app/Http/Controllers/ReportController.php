<?php
namespace App\Http\Controllers;

use App\Models\{Employee, AttendanceLog, LeaveRequest, Payslip, PerformanceReview, TrainingEnrollment, Department, LeaveType, PerformanceCycle};
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index() { return view('reports.index'); }

    public function employees(Request $request)
    {
        $employees = Employee::with(['department','designation'])
            ->when($request->department_id, fn($q) => $q->where('department_id', $request->department_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->type, fn($q) => $q->where('employment_type', $request->type))
            ->paginate(30);
        $departments = Department::all();
        $stats = ['total' => Employee::count(), 'active' => Employee::where('status','active')->count(), 'on_leave' => Employee::where('status','on_leave')->count(), 'terminated' => Employee::where('status','terminated')->count()];
        return view('reports.employees', compact('employees','departments','stats'));
    }

    public function attendance(Request $request)
    {
        $month = $request->month ?? now()->month;
        $year  = $request->year ?? now()->year;
        $deptId = $request->department_id;

        $activeEmployees = Employee::with('department')
            ->when($deptId, fn($q) => $q->where('department_id', $deptId))
            ->where('status','active')->get();

        $summary = $activeEmployees->map(function ($emp) use ($month, $year) {
            $logs    = AttendanceLog::where('employee_id', $emp->id)->whereYear('date', $year)->whereMonth('date', $month)->get();
            $present = $logs->where('status','present')->count();
            $absent  = $logs->where('status','absent')->count();
            $late    = $logs->where('status','late')->count();
            $overtime= $logs->sum('overtime_hours');
            $workDays= now()->setYear($year)->setMonth($month)->daysInMonth;
            $pct     = $workDays > 0 ? round(($present / $workDays) * 100, 1) : 0;
            return ['employee' => $emp, 'present' => $present, 'absent' => $absent, 'late' => $late, 'overtime' => $overtime, 'pct' => $pct];
        });

        $stats   = ['present'=>$summary->sum('present'),'absent'=>$summary->sum('absent'),'late'=>$summary->sum('late'),'half-day'=>0,'overtime'=>$summary->sum('overtime_hours')];
        $departments = Department::all();
        return view('reports.attendance', compact('summary','stats','departments'));
    }

    public function leave(Request $request)
    {
        $leaves = LeaveRequest::with(['employee','leaveType','approver'])
            ->when($request->year, fn($q) => $q->whereYear('from_date', $request->year))
            ->when($request->leave_type_id, fn($q) => $q->where('leave_type_id', $request->leave_type_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')->paginate(30);
        $leaveTypes = LeaveType::all();
        return view('reports.leave', compact('leaves','leaveTypes'));
    }

    public function payroll(Request $request)
    {
        $month   = $request->month ?? now()->month;
        $year    = $request->year ?? now()->year;
        $payslips = Payslip::with('employee')->whereHas('payrollRun', fn($q) => $q->where('month', $month)->where('year', $year))->paginate(30);
        $totals   = ['gross' => $payslips->sum('gross_salary'), 'deductions' => $payslips->sum('total_deductions'), 'tax' => $payslips->sum('tax_amount'), 'net' => $payslips->sum('net_salary')];
        return view('reports.payroll', compact('payslips','totals'));
    }

    public function performance(Request $request)
    {
        $reviews = PerformanceReview::with(['employee.department','cycle'])
            ->when($request->cycle_id, fn($q) => $q->where('cycle_id', $request->cycle_id))
            ->when($request->department_id, fn($q) => $q->whereHas('employee', fn($e) => $e->where('department_id', $request->department_id)))
            ->orderByDesc('total_score')->paginate(30);
        $cycles      = PerformanceCycle::all();
        $departments = Department::all();
        return view('reports.performance', compact('reviews','cycles','departments'));
    }

    public function training(Request $request)
    {
        $enrollments = TrainingEnrollment::with(['employee','course'])
            ->orderByDesc('created_at')->paginate(30);
        $stats = ['total_enrollments' => TrainingEnrollment::count(), 'completed' => TrainingEnrollment::where('status','completed')->count(), 'certifications' => \App\Models\Certification::count()];
        return view('reports.training', compact('enrollments','stats'));
    }

    public function export(Request $request)
    {
        // Simple CSV export fallback
        $type = $request->type;
        return back()->with('info', 'Export feature coming soon.');
    }

    public function exportEmployees()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\EmployeesExport,
            'employees-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    public function exportAttendance(Request $request)
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\AttendanceExport($request->all()),
            'attendance-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    public function exportLeave()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\LeaveExport,
            'leave-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    public function importForm()
    {
        return view('reports.import');
    }

    public function importEmployees(Request $request)
    {
        $request->validate(['file' => 'required|mimes:xlsx,xls,csv|max:5120']);
        \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\EmployeesImport, $request->file('file'));
        return back()->with('success', 'Employees imported successfully.');
    }
}