<?php
namespace App\Console\Commands;

use App\Models\{RatingScale, RatingScaleBand};
use Illuminate\Console\Command;

/**
 * Creates the starting rating scales.
 *
 * "Standard 1-5" reproduces the constants that were hardcoded on the Appraisal
 * model, thresholds included, so every card written before scales existed keeps
 * scoring and banding exactly as it did. The second scale is there to prove the
 * machinery is real rather than decorative, and can be deleted.
 *
 * Safe to run again: scales are matched on name and their bands rewritten.
 */
class SeedRatingScales extends Command
{
    protected $signature   = 'hrms:seed-rating-scales';
    protected $description = 'Create the default appraisal rating scales';

    private const SCALES = [
        [
            'name'        => 'Standard 1-5',
            'description' => 'The default Mastermind scale used on the balanced score card.',
            'max_points'  => 5,
            'is_default'  => true,
            'bands'       => [
                // min_percent matches the old bandFor(): a score has to clear the
                // floor, so 95.5% is still Very Good and 96% is Excellent.
                [1, 'Poor',      0,  'Below 50%'],
                [2, 'Fair',      51, '51 - 65%'],
                [3, 'Good',      66, '66 - 75%'],
                [4, 'Very Good', 76, '76 - 95%'],
                [5, 'Excellent', 96, 'Above 96%'],
            ],
        ],
        [
            'name'        => 'Expanded 1-10',
            'description' => 'A ten point scale for roles needing finer separation.',
            'max_points'  => 10,
            'is_default'  => false,
            'bands'       => [
                [1,  'Unsatisfactory',       0,   'Below 20%'],
                [2,  'Poor',                 20,  '20 - 29%'],
                [3,  'Below Expectations',   30,  '30 - 39%'],
                [4,  'Developing',           40,  '40 - 49%'],
                [5,  'Fair',                 50,  '50 - 59%'],
                [6,  'Meets Expectations',   60,  '60 - 69%'],
                [7,  'Good',                 70,  '70 - 79%'],
                [8,  'Very Good',            80,  '80 - 89%'],
                [9,  'Excellent',            90,  '90 - 94%'],
                [10, 'Outstanding',          95,  '95% and above'],
            ],
        ],
    ];

    public function handle(): int
    {
        foreach (self::SCALES as $spec) {
            $scale = RatingScale::updateOrCreate(
                ['name' => $spec['name']],
                [
                    'description' => $spec['description'],
                    'max_points'  => $spec['max_points'],
                    'is_active'   => true,
                ]
            );

            // Rewritten rather than merged: a band list that half-updates is
            // worse than one replaced outright.
            $scale->bands()->delete();

            foreach ($spec['bands'] as [$points, $label, $min, $range]) {
                RatingScaleBand::create([
                    'rating_scale_id' => $scale->id,
                    'points'          => $points,
                    'label'           => $label,
                    'min_percent'     => $min,
                    'range_label'     => $range,
                ]);
            }

            $this->line("  {$scale->name}: {$scale->max_points} points, "
                . count($spec['bands']) . ' bands');

            if ($spec['is_default']) $scale->makeDefault();
        }

        $default = RatingScale::default();
        $this->info('Default scale: ' . ($default?->name ?? 'none'));

        return Command::SUCCESS;
    }
}
