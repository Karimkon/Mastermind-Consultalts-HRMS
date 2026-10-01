<?php
namespace App\Console\Commands;

use App\Models\Candidate;
use App\Services\ApplicantOriginService;
use Illuminate\Console\Command;

/**
 * Sweeps up applications whose origin was never resolved.
 *
 * The lookup normally runs on the queue the moment somebody applies. This is
 * the safety net for the ones that did not: the queue was not drained, the
 * lookup service was down, or the row predates the feature entirely.
 */
class ResolveApplicantOrigins extends Command
{
    protected $signature = 'hrms:resolve-applicant-origins
                            {--limit=40 : How many to resolve on this pass}';

    protected $description = 'Work out which part of the country pending applications came from';

    public function handle(ApplicantOriginService $origins): int
    {
        // 45 lookups a minute is the free allowance, so a pass stays well
        // inside it and the next pass takes the rest.
        $pending = Candidate::whereNull('origin_resolved_at')
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($pending->isEmpty()) {
            $this->info('Nothing to resolve.');
            return self::SUCCESS;
        }

        $placed = 0;
        foreach ($pending as $candidate) {
            if ($origins->resolve($candidate)) {
                $placed++;
                $this->line("  #{$candidate->id} -> {$candidate->origin_label}");
            }
        }

        $this->info("Checked {$pending->count()}, placed {$placed}.");
        return self::SUCCESS;
    }
}
