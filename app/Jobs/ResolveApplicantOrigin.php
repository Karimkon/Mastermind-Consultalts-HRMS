<?php

namespace App\Jobs;

use App\Models\Candidate;
use App\Services\ApplicantOriginService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Puts one application on the map, after the applicant has gone.
 *
 * Queued so the lookup to a third party never sits between somebody pressing
 * Submit and seeing their reference number. If it fails there is a sweep
 * command that picks up anything left unresolved.
 */
class ResolveApplicantOrigin implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $candidateId) {}

    public function handle(ApplicantOriginService $origins): void
    {
        $candidate = Candidate::find($this->candidateId);
        if ($candidate) $origins->resolve($candidate);
    }
}
