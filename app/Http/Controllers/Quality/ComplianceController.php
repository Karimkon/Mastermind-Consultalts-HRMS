<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Models\QualityCheck;
use App\Models\QualityCheckResult;
use App\Models\QualityCheckRun;
use App\Models\QualityNonconformity;
use Illuminate\Support\Facades\DB;

class ComplianceController extends Controller
{
    /** The statutory items this screen tracks, across functions. */
    private const STATUTORY = [
        'employee.missing_nssf',
        'employee.missing_tin',
        'compliance.missing_national_id',
        'compliance.expatriate_no_passport',
        'compliance.contract_expired',
        'compliance.contract_expiring',
        'payroll.inactive_employee_paid',
    ];

    public function index()
    {
        $latest = QualityCheckRun::where('status', 'completed')->latest('completed_at')->first();

        $failCounts = [];
        if ($latest) {
            $failCounts = QualityCheckResult::query()
                ->select('quality_check_id', DB::raw('COUNT(*) as fails'))
                ->where('quality_check_run_id', $latest->id)
                ->where('status', 'fail')
                ->groupBy('quality_check_id')
                ->pluck('fails', 'quality_check_id')->all();
        }

        $checks = QualityCheck::whereIn('engine_key', self::STATUTORY)
            ->with('standard')->get()
            ->map(fn ($c) => [
                'name' => $c->name,
                'function' => $c->hr_function,
                'severity' => $c->severity,
                'standard' => $c->standard?->code,
                'fails' => (int) ($failCounts[$c->id] ?? 0),
                'clean' => ! array_key_exists($c->id, $failCounts),
            ])
            ->sortByDesc('fails')->values()->all();

        $complianceScore = $latest?->summary['compliance']['score'] ?? null;

        $openNc = QualityNonconformity::where('hr_function', 'compliance')
            ->whereIn('status', QualityNonconformity::OPEN_STATES)
            ->orderBySeverity()
            ->get();

        $totalFlagged = array_sum(array_column($checks, 'fails'));

        return view('quality.compliance.index', compact(
            'latest', 'checks', 'complianceScore', 'openNc', 'totalFlagged'
        ));
    }
}
