<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Employee, AttendanceLog, LeaveRequest, PayrollRun, JobPosting, Candidate, Meeting, TrainingCourse, Client};
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardApiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('client')) {
            return response()->json($this->clientDashboard($user));
        }
        if ($user->hasRole(['super-admin', 'hr-admin', 'manager'])) {
            return response()->json($this->adminDashboard($user));
        }
        if ($user->hasRole('payroll-officer')) {
            return response()->json($this->payrollDashboard());
        }
        if ($user->hasRole('recruiter')) {
            return response()->json($this->recruiterDashboard());
        }
        return response()->json($this->employeeDashboard($user));
    }

    private function adminDashboard($user): array
    {
        $today = Carbon::today();
        $month = Carbon::now()->startOfMonth();

        // Monthly attendance chart (last 7 days)
        $attendanceChart = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $attendanceChart[] = [
                'date'  => $date->format('D'),
                'count' => AttendanceLog::whereDate('clock_in', $date)->count(),
            ];
        }

        return [
            'role' => 'admin',
            'stats' => [
                'total_employees'  => Employee::where('status', 'active')->count(),
                'present_today'    => AttendanceLog::whereDate('clock_in', $today)->whereNull('clock_out')->count()
                                    + AttendanceLog::whereDate('clock_in', $today)->whereNotNull('clock_out')->count(),
                'on_leave'         => Employee::where('status', 'on_leave')->count(),
                'pending_leaves'   => LeaveRequest::where('status', 'pending')->count(),
                'open_jobs'        => JobPosting::where('status', 'open')->count(),
                'meetings_today'   => Meeting::whereDate('start_at', $today)->count(),
                'active_trainings' => TrainingCourse::where('is_active', true)->count(),
                'payroll_runs'     => PayrollRun::whereMonth('created_at', $month->month)->count(),
            ],
            'attendance_chart' => $attendanceChart,
            'recent_employees' => Employee::with('user', 'department')
                ->latest()->limit(5)
                ->get()->map(fn($e) => [
                    'id'         => $e->id,
                    'name'       => $e->full_name,
                    'emp_number' => $e->emp_number,
                    'department' => $e->department?->name,
                    'avatar_url' => $e->user?->avatar_url,
                    'hire_date'  => $e->hire_date?->format('Y-m-d'),
                ]),
            'pending_leaves' => LeaveRequest::with('employee.user', 'leaveType')
                ->where('status', 'pending')->limit(5)->latest()
                ->get()->map(fn($l) => [
                    'id'          => $l->id,
                    'employee'    => $l->employee->full_name,
                    'avatar_url'  => $l->employee->user?->avatar_url,
                    'leave_type'  => $l->leaveType->name,
                    'from_date'   => $l->from_date->format('Y-m-d'),
                    'to_date'     => $l->to_date->format('Y-m-d'),
                    'days'        => $l->days_count,
                ]),
            'upcoming_meetings' => Meeting::with('participants.employee')
                ->where('start_at', '>=', now())
                ->orderBy('start_at')->limit(5)->get()
                ->map(fn($m) => [
                    'id'       => $m->id,
                    'title'    => $m->title,
                    'start_at' => $m->start_at,
                    'type'     => $m->type,
                ]),
        ];
    }

    private function employeeDashboard($user): array
    {
        $employee = $user->employee;
        if (!$employee) {
            return ['role' => 'employee', 'stats' => [], 'message' => 'No employee profile found.'];
        }

        $today = Carbon::today();
        $year  = Carbon::now()->year;

        $todayLog = AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('clock_in', $today)->latest()->first();

        $leaveBalances = \App\Models\LeaveBalance::with('leaveType')
            ->where('employee_id', $employee->id)->where('year', $year)->get()
            ->map(fn($b) => [
                'type'       => $b->leaveType->name,
                'color'      => $b->leaveType->color,
                'total'      => $b->total_days,
                'used'       => $b->used_days,
                'pending'    => $b->pending_days,
                'remaining'  => max(0, $b->total_days - $b->used_days - $b->pending_days),
            ]);

        return [
            'role' => 'employee',
            'stats' => [
                'attendance_this_month' => AttendanceLog::where('employee_id', $employee->id)
                    ->whereMonth('clock_in', now()->month)->count(),
                'leaves_taken'          => LeaveRequest::where('employee_id', $employee->id)
                    ->where('status', 'approved')->whereYear('from_date', $year)->count(),
                'training_enrolled'     => \App\Models\TrainingEnrollment::where('employee_id', $employee->id)->count(),
                'meetings_upcoming'     => Meeting::whereHas('participants', fn($q) => $q->where('employee_id', $employee->id))
                    ->where('start_at', '>=', now())->count(),
            ],
            'today_attendance' => $todayLog ? [
                'id'        => $todayLog->id,
                'clock_in'  => $todayLog->clock_in,
                'clock_out' => $todayLog->clock_out,
                'status'    => $todayLog->clock_out ? 'clocked_out' : 'clocked_in',
            ] : null,
            'leave_balances' => $leaveBalances,
            'recent_leaves'  => LeaveRequest::with('leaveType')
                ->where('employee_id', $employee->id)->latest()->limit(5)->get()
                ->map(fn($l) => [
                    'id'         => $l->id,
                    'leave_type' => $l->leaveType->name,
                    'from_date'  => $l->from_date->format('Y-m-d'),
                    'to_date'    => $l->to_date->format('Y-m-d'),
                    'days'       => $l->days_count,
                    'status'     => $l->status,
                ]),
            'recent_payslips' => \App\Models\Payslip::with('payrollRun')
                ->where('employee_id', $employee->id)->latest()->limit(3)->get()
                ->map(fn($p) => [
                    'id'          => $p->id,
                    'period'      => $p->payrollRun?->title,
                    'net_pay'     => $p->net_pay,
                    'paid_on'     => $p->created_at->format('Y-m-d'),
                ]),
        ];
    }

    private function payrollDashboard(): array
    {
        return [
            'role' => 'payroll-officer',
            'stats' => [
                'total_employees' => Employee::where('status', 'active')->count(),
                'pending_runs'    => PayrollRun::where('status', 'draft')->count(),
                'processed_runs'  => PayrollRun::where('status', 'processed')->count(),
                'approved_runs'   => PayrollRun::where('status', 'approved')->count(),
            ],
            'recent_runs' => PayrollRun::latest()->limit(8)->get()->map(fn($r) => [
                'id'      => $r->id,
                'title'   => $r->title,
                'month'   => $r->month,
                'year'    => $r->year,
                'status'  => $r->status,
            ]),
        ];
    }

    private function recruiterDashboard(): array
    {
        $today = Carbon::today();
        return [
            'role' => 'recruiter',
            'stats' => [
                'open_jobs'        => JobPosting::where('status', 'open')->count(),
                'total_candidates' => Candidate::count(),
                'interviews_today' => \App\Models\Interview::whereDate('scheduled_at', $today)->count(),
                'hired_month'      => Candidate::where('status', 'hired')
                    ->whereMonth('updated_at', now()->month)->count(),
            ],
            'recent_jobs' => JobPosting::with('department')->latest()->limit(5)->get()->map(fn($j) => [
                'id'         => $j->id,
                'title'      => $j->title,
                'department' => $j->department?->name,
                'status'     => $j->status,
                'candidates' => $j->candidates()->count(),
            ]),
        ];
    }

    private function clientDashboard($user): array
    {
        $client = Client::where('user_id', $user->id)->firstOrFail();
        $empIds = $client->employees()->pluck('employees.id');
        $jobIds = $client->jobPostings()->pluck('job_postings.id');

        return [
            'role' => 'client',
            'client' => ['id' => $client->id, 'company_name' => $client->company_name],
            'stats' => [
                'pending_leaves'    => LeaveRequest::whereIn('employee_id', $empIds)
                    ->where('client_approval_required', true)->where('client_approval_status', 'pending')->count(),
                'pending_shortlist' => Candidate::whereIn('job_posting_id', $jobIds)
                    ->where('status', 'shortlisted')->whereNull('client_shortlist_status')->count(),
                'approved_leaves'   => LeaveRequest::whereIn('employee_id', $empIds)
                    ->where('client_approval_status', 'approved')->count(),
                'hired'             => Candidate::whereIn('job_posting_id', $jobIds)->where('status', 'hired')->count(),
            ],
        ];
    }
}
