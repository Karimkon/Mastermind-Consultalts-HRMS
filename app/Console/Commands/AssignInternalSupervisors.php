<?php
namespace App\Console\Commands;

use App\Models\{Employee, OrgPosition};
use Illuminate\Console\Command;

/**
 * Sets the reporting line for Mastermind's own staff from the chart.
 *
 * Client-site staff report to their Account Manager, which is a flat rule and
 * lives in its own command. Head Office is a hierarchy, so a person's
 * supervisor is whoever holds the nearest position above theirs.
 *
 * Vacancies are walked through rather than treated as a dead end: with General
 * Manager empty, the Accounts and Finance Officer reports to the Managing
 * Director, and moves under the GM automatically once somebody is seated there
 * and this is run again.
 */
class AssignInternalSupervisors extends Command
{
    protected $signature = 'hrms:assign-internal-supervisors
                            {--dry-run   : Report what would change without writing}
                            {--overwrite : Also replace supervisors that are already set}';

    protected $description = "Set Head Office reporting lines from the organisation chart";

    public function handle(): int
    {
        $dry       = (bool) $this->option('dry-run');
        $overwrite = (bool) $this->option('overwrite');

        $positions = OrgPosition::whereNull('client_id')->with('employees')->get();
        if ($positions->isEmpty()) {
            $this->warn('No internal positions found. Run hrms:seed-org-structure first.');
            return Command::SUCCESS;
        }

        $byId = $positions->keyBy('id');
        $set  = 0;
        $top  = 0;

        foreach ($positions as $position) {
            if ($position->employees->isEmpty()) continue;

            $supervisor = $this->nearestHolder($position, $byId);

            foreach ($position->employees as $employee) {
                if (! $supervisor) {
                    // The MD answers to the Board, which is not an employee, so
                    // an empty supervisor here is the correct answer rather than
                    // a gap to be filled.
                    $this->line("  {$employee->full_name} ({$position->title}): reports to the Board — left empty");
                    $top++;
                    continue;
                }

                if ($supervisor->id === $employee->id) continue;          // never yourself
                if ($employee->manager_id && ! $overwrite) continue;      // don't disturb a set line

                $this->line("  {$employee->full_name} ({$position->title}) → {$supervisor->full_name}");

                if (! $dry) {
                    $employee->forceFill(['manager_id' => $supervisor->id])->save();
                }
                $set++;
            }
        }

        $this->info($dry
            ? "Dry run: {$set} reporting line(s) would be set, {$top} left empty at the top. Nothing written."
            : "Done. Reporting lines set: {$set}. Left empty at the top: {$top}.");

        return Command::SUCCESS;
    }

    /**
     * Walk up until a position has somebody in it.
     *
     * Bounded rather than trusting the data to be a tree: a parent_id that
     * pointed into a cycle would otherwise loop here forever.
     */
    private function nearestHolder(OrgPosition $position, $byId): ?Employee
    {
        $node  = $byId[$position->parent_id] ?? null;
        $guard = 0;

        while ($node && $guard++ < 20) {
            if ($node->employees->isNotEmpty()) {
                return $node->employees->first();
            }
            $node = $byId[$node->parent_id] ?? null;
        }

        return null;
    }
}
