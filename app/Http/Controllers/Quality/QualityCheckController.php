<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Models\QualityCheck;
use App\Models\QualityCheckResult;
use App\Models\QualityCheckRun;
use App\Services\Quality\QualityEngine;
use Illuminate\Http\Request;

class QualityCheckController extends Controller
{
    /** Tune a check's contribution to the score and whether it raises NCs. */
    public function updateConfig(Request $request, QualityCheck $check)
    {
        $data = $request->validate([
            'severity' => 'required|in:low,medium,high,critical',
            'weight' => 'required|integer|min:1|max:100',
            'auto_raise_nc' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $check->update([
            'severity' => $data['severity'],
            'weight' => $data['weight'],
            'auto_raise_nc' => $request->boolean('auto_raise_nc'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', "Check \"{$check->name}\" updated.");
    }

    public function index()
    {
        $checks = QualityCheck::with('standard')
            ->orderBy('hr_function')->orderByDesc('weight')->get()
            ->groupBy('hr_function');

        $latest = QualityCheckRun::where('status', 'completed')->latest('completed_at')->first();

        return view('quality.checks.index', compact('checks', 'latest'));
    }

    /** Fire the engine. Optionally scoped to one HR function. */
    public function run(Request $request, QualityEngine $engine)
    {
        $scope = $request->input('scope', 'all');

        $run = $engine->run(
            scope: $scope,
            userId: $request->user()?->id,
            trigger: 'manual',
        );

        return redirect()
            ->route('quality.checks.results', ['run' => $run->id])
            ->with('success', "Quality scan complete. Score {$run->score}% - {$run->failed} issue(s), {$run->nonconformities_raised} new non-conformity(ies).");
    }

    public function results(Request $request, ?QualityCheckRun $run = null)
    {
        $run ??= QualityCheckRun::where('status', 'completed')->latest('completed_at')->first();

        abort_if(! $run, 404, 'No quality scan has been run yet.');

        $status = $request->input('status', 'fail');
        $checkId = $request->input('check');

        $results = QualityCheckResult::with('check')
            ->where('quality_check_run_id', $run->id)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($checkId, fn ($q) => $q->where('quality_check_id', $checkId))
            ->orderBy('quality_check_id')
            ->paginate(50)->withQueryString();

        $checksInRun = QualityCheckResult::where('quality_check_run_id', $run->id)
            ->with('check')->get()->pluck('check')->unique('id')->filter()->values();

        return view('quality.checks.results', compact('run', 'results', 'status', 'checkId', 'checksInRun'));
    }
}
