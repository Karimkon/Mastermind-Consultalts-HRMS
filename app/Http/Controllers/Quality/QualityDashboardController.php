<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Models\QualityCheckResult;
use App\Models\QualityCheckRun;
use App\Models\QualityNonconformity;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;

class QualityDashboardController extends Controller
{
    public function index()
    {
        $latest = QualityCheckRun::where('status', 'completed')->latest('completed_at')->first();

        $open = QualityNonconformity::whereIn('status', QualityNonconformity::OPEN_STATES);
        $ncBySeverity = (clone $open)
            ->select('severity', DB::raw('COUNT(*) as c'))
            ->groupBy('severity')->pluck('c', 'severity');

        $functions = $this->functionScores($latest);
        $topIssues = $this->topIssues($latest);
        $departments = $this->departmentScores($latest);

        $recentRuns = QualityCheckRun::where('status', 'completed')
            ->latest('completed_at')->limit(8)->get();

        $recentNc = QualityNonconformity::with('check')
            ->whereIn('status', QualityNonconformity::OPEN_STATES)
            ->orderBySeverity()
            ->latest('detected_at')->limit(8)->get();

        return view('quality.dashboard', [
            'latest' => $latest,
            'overall' => $latest?->score,
            'openTotal' => (clone $open)->count(),
            'ncBySeverity' => $ncBySeverity,
            'functions' => $functions,
            'topIssues' => $topIssues,
            'departments' => $departments,
            'recentRuns' => $recentRuns,
            'recentNc' => $recentNc,
        ]);
    }

    /** Per-HR-function scores from the latest run's stored summary. */
    private function functionScores(?QualityCheckRun $run): array
    {
        $labels = [
            'employee_central' => 'Employee Central',
            'payroll' => 'Payroll',
            'recruitment' => 'Recruitment',
            'attendance' => 'Attendance',
            'leave' => 'Leave',
            'performance' => 'Performance',
            'compliance' => 'Compliance',
        ];
        $out = [];
        foreach (($run?->summary ?? []) as $fn => $d) {
            $out[] = [
                'key' => $fn,
                'label' => $labels[$fn] ?? ucwords(str_replace('_', ' ', $fn)),
                'score' => (float) ($d['score'] ?? 0),
                'checks' => $d['checks'] ?? 0,
                'failed' => $d['failed'] ?? 0,
            ];
        }
        usort($out, fn ($a, $b) => $a['score'] <=> $b['score']);
        return $out;
    }

    /** The checks with the most failures in the latest run. */
    private function topIssues(?QualityCheckRun $run): array
    {
        if (! $run) return [];
        return QualityCheckResult::query()
            ->select('quality_check_id', DB::raw('COUNT(*) as fails'))
            ->where('quality_check_run_id', $run->id)
            ->where('status', 'fail')
            ->groupBy('quality_check_id')
            ->orderByDesc('fails')
            ->with('check')
            ->limit(8)->get()
            ->map(fn ($r) => [
                'name' => $r->check?->name ?? 'Check #' . $r->quality_check_id,
                'function' => $r->check?->hr_function,
                'severity' => $r->check?->severity,
                'fails' => $r->fails,
            ])->all();
    }

    /**
     * Department quality: of each department's active employees, how many are
     * clean of any failing employee-level check in the latest run.
     */
    private function departmentScores(?QualityCheckRun $run): array
    {
        if (! $run) return [];

        $active = DB::table('employees')
            ->whereNull('deleted_at')->where('status', 'active')
            ->select('department_id', DB::raw('COUNT(*) as total'))
            ->groupBy('department_id')->pluck('total', 'department_id');

        // Employees with at least one failing employee-level result this run.
        $failing = DB::table('quality_check_results')
            ->join('employees', 'quality_check_results.subject_id', '=', 'employees.id')
            ->where('quality_check_results.quality_check_run_id', $run->id)
            ->where('quality_check_results.status', 'fail')
            ->where('quality_check_results.subject_type', Employee::class)
            ->whereNull('employees.deleted_at')->where('employees.status', 'active')
            ->select('employees.department_id', DB::raw('COUNT(DISTINCT employees.id) as bad'))
            ->groupBy('employees.department_id')->pluck('bad', 'department_id');

        $names = DB::table('departments')->pluck('name', 'id');

        $out = [];
        foreach ($active as $deptId => $total) {
            if (! $total) continue;
            $bad = (int) ($failing[$deptId] ?? 0);
            $score = round((1 - $bad / $total) * 100, 1);
            $out[] = [
                'name' => $deptId ? ($names[$deptId] ?? 'Dept #' . $deptId) : 'Unassigned',
                'total' => (int) $total,
                'flagged' => $bad,
                'score' => $score,
            ];
        }
        usort($out, fn ($a, $b) => $a['score'] <=> $b['score']);
        return $out;
    }
}
