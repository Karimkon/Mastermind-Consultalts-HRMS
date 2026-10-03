<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Models\QualityAudit;
use App\Models\QualityCheckRun;
use App\Models\QualityCorrectiveAction;
use App\Models\QualityNonconformity;
use App\Models\QualityScoreSnapshot;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        // Overall score trend (last 30 daily points).
        $trend = QualityScoreSnapshot::where('scope', 'overall')
            ->orderBy('snapshot_date')
            ->limit(60)->get(['snapshot_date', 'score', 'open_nc'])
            ->map(fn ($s) => [
                'date' => $s->snapshot_date->format('d M'),
                'score' => (float) $s->score,
                'open_nc' => $s->open_nc,
            ])->values()->all();

        // Latest score per function (most recent snapshot date).
        $latestFnDate = QualityScoreSnapshot::where('scope', 'function')->max('snapshot_date');
        $functions = $latestFnDate
            ? QualityScoreSnapshot::where('scope', 'function')->whereDate('snapshot_date', $latestFnDate)
                ->orderBy('score')->get(['label', 'score', 'failed'])
            : collect();

        $ncByStatus = QualityNonconformity::select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')->pluck('c', 'status');
        $ncBySeverity = QualityNonconformity::whereIn('status', QualityNonconformity::OPEN_STATES)
            ->select('severity', DB::raw('COUNT(*) as c'))->groupBy('severity')->pluck('c', 'severity');

        $capaByStatus = QualityCorrectiveAction::select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')->pluck('c', 'status');

        $audits = QualityAudit::with('auditor')->latest()->limit(10)->get();
        $runs = QualityCheckRun::where('status', 'completed')->latest('completed_at')->limit(10)->get();

        return view('quality.reports.index', compact(
            'trend', 'functions', 'ncByStatus', 'ncBySeverity', 'capaByStatus', 'audits', 'runs'
        ));
    }
}
