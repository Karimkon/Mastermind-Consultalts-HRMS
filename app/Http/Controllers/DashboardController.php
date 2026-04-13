<?php
namespace App\Http\Controllers;

use App\Models\{Employee, AttendanceLog, LeaveRequest, Meeting, PayrollRun, TrainingEnrollment, Payslip, LeaveBalance};
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Employee role: show only personal data
        if ($user->hasRole('employee')) {
            return $this->employeeDashboard($user);
        }

        // Payroll officer: payroll-focused
        if ($user->hasRole('payroll-officer')) {
            return $this->payrollDashboard();
        }

        // Recruiter: recruitment-focused
        if ($user->hasRole('recruiter')) {
            return $this->recruiterDashboard();
        }

        // Super admin, HR admin, Manager: full org dashboard
        return $this->adminDashboard();
    }

    private function adminDashboard()
    {
        $stats = [
            'total_employees'   => Employee::where('status', 'active')->count(),
            'present_today'     => AttendanceLog::whereDate('date', today())->where('status', 'present')->count(),
            'on_leave_today'    => LeaveRequest::where('status', 'approved')
                                    ->where('from_date', '<=', today())->where('to_date', '>=', today())->count(),
            'pending_leaves'    => LeaveRequest::where('status', 'pending')->count(),
            'open_jobs'         => \App\Models\JobPosting::where('status', 'open')->count(),
            'meetings_today'    => Meeting::whereDate('start_at', today())->count(),
            'trainings_active'  => TrainingEnrollment::where('status', 'enrolled')->distinct('employee_id')->count(),
            'this_month_payroll'=> PayrollRun::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
        ];

        $pendingLeaves   = LeaveRequest::with(['employee.department', 'leaveType'])
            ->where('status', 'pending')->latest()->limit(5)->get();
        $recentEmployees = Employee::with(['department', 'designation'])->latest()->limit(5)->get();
        $upcomingMeetings= Meeting::with('organizer')->where('start_at', '>=', now())->orderBy('start_at')->limit(5)->get();
        $todayAttendance = AttendanceLog::with('employee')->whereDate('date', today())
            ->whereNotNull('clock_in')->latest('clock_in')->limit(8)->get();

        return view('dashboard.index', compact('stats','pendingLeaves','recentEmployees','upcomingMeetings','todayAttendance'));
    }

    private function employeeDashboard($user)
    {
        $employee = $user->employee;

        if (!$employee) {
            return view('dashboard.employee', ['employee' => null, 'stats' => [], 'leaves' => collect(), 'payslips' => collect(), 'enrollments' => collect(), 'meetings' => collect()]);
        }

        $now   = now();
        $month = $now->month;
        $year  = $now->year;

        // My attendance this month
        $myAttendance = AttendanceLog::where('employee_id', $employee->id)
            ->whereYear('date', $year)->whereMonth('date', $month)->get();

        $stats = [
            'present_this_month' => $myAttendance->where('status', 'present')->count(),
            'absent_this_month'  => $myAttendance->where('status', 'absent')->count(),
            'late_this_month'    => $myAttendance->where('status', 'late')->count(),
        ];

        // My leave balances
        $leaveBalances = LeaveBalance::with('leaveType')
            ->where('employee_id', $employee->id)->where('year', $year)->get();

        // My recent leave requests
        $leaves = LeaveRequest::with('leaveType')
            ->where('employee_id', $employee->id)->latest()->limit(5)->get();

        // My payslips
        $payslips = Payslip::with('payrollRun')
            ->where('employee_id', $employee->id)->latest()->limit(6)->get();

        // My training enrollments
        $enrollments = TrainingEnrollment::with('course')
            ->where('employee_id', $employee->id)->latest()->limit(5)->get();

        // My upcoming meetings
        $meetings = Meeting::whereHas('participants', fn($q) => $q->where('employee_id', $employee->id))
            ->where('start_at', '>=', $now)->orderBy('start_at')->limit(5)->get();

        // Today's clock status
        $todayLog = AttendanceLog::where('employee_id', $employee->id)->whereDate('date', today())->first();

        return view('dashboard.employee', compact('employee','stats','leaveBalances','leaves','payslips','enrollments','meetings','todayLog'));
    }

    private function payrollDashboard()
    {
        $stats = [
            'total_employees' => Employee::where('status', 'active')->count(),
            'pending_runs'    => PayrollRun::where('status', 'draft')->count(),
            'processed_runs'  => PayrollRun::where('status', 'processed')->count(),
            'approved_runs'   => PayrollRun::where('status', 'approved')->count(),
        ];
        $recentRuns      = PayrollRun::latest()->limit(5)->get();
        $pendingLeaves   = collect();
        $recentEmployees = collect();
        $upcomingMeetings= collect();
        $todayAttendance = collect();
        return view('dashboard.index', compact('stats','pendingLeaves','recentEmployees','upcomingMeetings','todayAttendance','recentRuns'));
    }

    private function recruiterDashboard()
    {
        $stats = [
            'open_jobs'        => \App\Models\JobPosting::where('status', 'open')->count(),
            'total_candidates' => \App\Models\Candidate::count(),
            'interviews_today' => \App\Models\Interview::whereDate('scheduled_at', today())->count(),
            'hired_this_month' => \App\Models\Candidate::where('status', 'hired')->whereMonth('updated_at', now()->month)->count(),
        ];
        $pendingLeaves   = collect();
        $recentEmployees = collect();
        $upcomingMeetings= collect();
        $todayAttendance = collect();
        return view('dashboard.index', compact('stats','pendingLeaves','recentEmployees','upcomingMeetings','todayAttendance'));
    }
}
