<?php

namespace App\Services;

use App\Models\Candidate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Turns the IP an application arrived from into a place on the map.
 *
 * Deliberately never runs while somebody is applying. The lookup goes to a
 * third party, and a job application must not get slower — or fail — because
 * somebody else's service is slow or down. The IP is recorded at the time of
 * the application; the place is filled in afterwards, and an application whose
 * place is never resolved simply reads "Unknown".
 */
class ApplicantOriginService
{
    /** Private and loopback addresses resolve to nothing, so do not ask. */
    public function isRoutable(?string $ip): bool
    {
        return $ip !== null
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * Resolve one candidate's origin. Returns true when something was learnt.
     *
     * Marks `origin_resolved_at` either way, so an address that cannot be
     * placed is not retried on every pass forever.
     */
    public function resolve(Candidate $candidate): bool
    {
        if ($candidate->origin_resolved_at !== null) return false;

        if (! $this->isRoutable($candidate->ip_address)) {
            $candidate->forceFill(['origin_resolved_at' => now()])->save();
            return false;
        }

        try {
            // ip-api.com: no key, no account, 45 requests a minute from one
            // host — comfortably more than this will ever need.
            $response = Http::timeout(4)->get('http://ip-api.com/json/' . $candidate->ip_address, [
                'fields' => 'status,country,regionName,city',
            ]);

            if (! $response->ok() || ($response->json('status') !== 'success')) {
                $candidate->forceFill(['origin_resolved_at' => now()])->save();
                return false;
            }

            $candidate->forceFill([
                'country'            => $response->json('country'),
                'region'             => $response->json('regionName'),
                'city'               => $response->json('city'),
                'origin_resolved_at' => now(),
            ])->save();

            return true;
        } catch (\Throwable $e) {
            // Never let this surface. The application is already saved; the
            // place is a nicety on a report.
            report($e);
            $candidate->forceFill(['origin_resolved_at' => now()])->save();
            return false;
        }
    }

    /**
     * How applications break down by position and by place.
     *
     * Both halves of the brief's question in one pass, and the percentages are
     * of the whole so the top three can be read straight off.
     *
     * @return array{total:int, by_position:array, by_area:array}
     */
    public function breakdown(?int $jobId = null): array
    {
        $candidates = Candidate::query()
            ->when($jobId, fn ($q) => $q->where('job_posting_id', $jobId))
            ->with('jobPosting:id,title')
            ->get(['id', 'job_posting_id', 'country', 'region', 'city']);

        $total = $candidates->count();
        $share = fn (int $n) => $total ? round($n * 100 / $total, 1) : 0.0;

        $byPosition = $candidates
            ->groupBy(fn ($c) => $c->jobPosting?->title ?? 'Unknown position')
            ->map(fn ($g, $title) => ['label' => $title, 'count' => $g->count(), 'percent' => $share($g->count())])
            ->sortByDesc('count')->values()->all();

        $byArea = $candidates
            ->groupBy(function ($c) {
                // Region is the level the brief asked about; the country only
                // matters for the ones from outside Uganda.
                if ($c->region) return $c->country === 'Uganda' ? $c->region : $c->region . ', ' . $c->country;
                return $c->country ?: 'Unknown area';
            })
            ->map(fn ($g, $area) => ['label' => $area, 'count' => $g->count(), 'percent' => $share($g->count())])
            ->sortByDesc('count')->values()->all();

        return ['total' => $total, 'by_position' => $byPosition, 'by_area' => $byArea];
    }
}
