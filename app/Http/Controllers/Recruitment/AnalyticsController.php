<?php
namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\JobPosting;
use App\Services\ApplicantOriginService;
use Illuminate\Http\Request;

/**
 * Where applications come from, and what they are for.
 *
 * Two questions the brief asked and nothing could answer: how many
 * applications each position is getting, and which part of the country they
 * are arriving from. The second is worked out from the address an application
 * came in on, so it is approximate by nature - a phone on mobile data can
 * resolve to wherever the carrier's gateway sits. The page says so.
 */
class AnalyticsController extends Controller
{
    public function index(Request $request, ApplicantOriginService $origins)
    {
        $jobId = $request->integer('job') ?: null;

        $breakdown = $origins->breakdown($jobId);

        $jobs = JobPosting::orderByDesc('created_at')
            ->withCount('candidates')
            ->get(['id', 'title', 'status', 'is_public']);

        // How far the geography is actually filled in. Without this the page
        // would read as though every application came from "Unknown".
        $placed    = Candidate::whereNotNull('country')
            ->when($jobId, fn ($q) => $q->where('job_posting_id', $jobId))->count();
        $pending   = Candidate::whereNull('origin_resolved_at')
            ->when($jobId, fn ($q) => $q->where('job_posting_id', $jobId))->count();
        $noAddress = Candidate::whereNull('ip_address')
            ->when($jobId, fn ($q) => $q->where('job_posting_id', $jobId))->count();

        // The pipeline, by stage, for whichever slice is selected.
        $byStage = Candidate::query()
            ->when($jobId, fn ($q) => $q->where('job_posting_id', $jobId))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return view('recruitment.analytics.index', [
            'breakdown' => $breakdown,
            'jobs'      => $jobs,
            'jobId'     => $jobId,
            'placed'    => $placed,
            'pending'   => $pending,
            'noAddress' => $noAddress,
            'byStage'   => $byStage,
            'cities'    => $this->cities($jobId),
        ]);
    }

    /** The towns applications come from, under the regions. */
    private function cities(?int $jobId): array
    {
        return Candidate::query()
            ->whereNotNull('city')
            ->when($jobId, fn ($q) => $q->where('job_posting_id', $jobId))
            ->selectRaw('city, region, country, COUNT(*) as total')
            ->groupBy('city', 'region', 'country')
            ->orderByDesc('total')
            ->limit(15)
            ->get()
            ->map(fn ($r) => [
                'label' => trim($r->city . ($r->region && $r->region !== $r->city ? ', ' . $r->region : '')),
                'country' => $r->country,
                'count' => (int) $r->total,
            ])->all();
    }
}
