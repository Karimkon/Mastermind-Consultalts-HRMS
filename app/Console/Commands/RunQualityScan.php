<?php

namespace App\Console\Commands;

use App\Services\Quality\QualityEngine;
use Illuminate\Console\Command;

class RunQualityScan extends Command
{
    protected $signature = 'quality:scan {--scope=all : all, or one HR function}';

    protected $description = 'Run the Quality Engine across the HRMS, score it, and raise non-conformities';

    public function handle(QualityEngine $engine): int
    {
        $scope = $this->option('scope') ?: 'all';
        $this->info("Running quality scan (scope: {$scope})...");

        $run = $engine->run(scope: $scope, userId: null, trigger: 'scheduled');

        $this->table(
            ['Score', 'Scanned', 'Passed', 'Failed', 'Warnings', 'NCs raised'],
            [[$run->score . '%', $run->subjects_scanned, $run->passed, $run->failed, $run->warnings, $run->nonconformities_raised]]
        );

        return self::SUCCESS;
    }
}
