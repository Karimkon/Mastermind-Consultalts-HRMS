<?php
namespace App\Console\Commands;

use App\Models\{Employee, Notification, User};
use Illuminate\Console\Command;

/**
 * Warns HR that a probation is coming up for a decision, and nags when one has
 * been left past its end date.
 *
 * A probation that nobody reviews confirms the employee by silence, which is
 * the opposite of what a probation period is for - so the overdue pass matters
 * as much as the advance warning.
 */
class ProbationAlerts extends Command
{
    protected $signature = 'hrms:probation-alerts
                            {--days=45 : How many days before the end date to warn}
                            {--dry-run : List what would be sent without sending it}';

    protected $description = 'Warn HR before a probation period ends, and flag overdue reviews';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dry  = (bool) $this->option('dry-run');

        $hrUsers = User::role(['hr-admin', 'super-admin'])->get();
        if ($hrUsers->isEmpty()) {
            $this->warn('No hr-admin or super-admin users to notify.');
            return Command::SUCCESS;
        }

        $sent = 0;

        // ── Coming up ────────────────────────────────────────────────────────
        // Anyone whose end date falls between today and the notice window. The
        // sent-at stamp keeps this to one warning per employee rather than one
        // every day they sit inside the window.
        $upcoming = Employee::with(['user', 'department', 'designation'])
            ->where('probation_status', 'on_probation')
            ->whereNotNull('probation_end_date')
            ->whereBetween('probation_end_date', [today(), today()->copy()->addDays($days)])
            ->whereNull('probation_alert_sent_at')
            ->get();

        foreach ($upcoming as $employee) {
            $left = (int) today()->diffInDays($employee->probation_end_date, false);

            $this->line("  Ending in {$left} day(s): {$employee->full_name}"
                . " — {$employee->probation_end_date->format('d M Y')}");

            if ($dry) { continue; }

            foreach ($hrUsers as $hr) {
                $this->notify($hr->id,
                    "Probation ending in {$left} day(s): {$employee->full_name}",
                    "Probation ends {$employee->probation_end_date->format('d M Y')}. "
                    . 'Confirm, extend or end their employment before that date.',
                    $employee);
                $sent++;
            }

            $employee->forceFill(['probation_alert_sent_at' => now()])->save();
        }

        // ── Already overdue ──────────────────────────────────────────────────
        $overdue = Employee::with(['user', 'department'])
            ->where('probation_status', 'on_probation')
            ->whereNotNull('probation_end_date')
            ->whereDate('probation_end_date', '<', today())
            ->get();

        foreach ($overdue as $employee) {
            $late = (int) $employee->probation_end_date->diffInDays(today());

            $this->line("  OVERDUE by {$late} day(s): {$employee->full_name}");

            if ($dry) { continue; }

            foreach ($hrUsers as $hr) {
                $this->notify($hr->id,
                    "OVERDUE: {$employee->full_name}'s probation ended {$late} day(s) ago",
                    "Probation ended {$employee->probation_end_date->format('d M Y')} and is "
                    . 'still awaiting a decision.',
                    $employee);
                $sent++;
            }
        }

        $this->info($dry
            ? "Dry run: {$upcoming->count()} upcoming, {$overdue->count()} overdue. Nothing sent."
            : "Probation alerts done. Upcoming: {$upcoming->count()} | Overdue: "
              . "{$overdue->count()} | Notifications: {$sent}");

        return Command::SUCCESS;
    }

    private function notify(int $userId, string $title, string $body, Employee $employee): void
    {
        Notification::create([
            'user_id' => $userId,
            'type'    => 'probation_due',
            'title'   => $title,
            'body'    => $body,
            // The bell links straight to the review screen, so the warning is
            // one click from the decision it is asking for.
            'data'    => [
                'employee_id' => $employee->id,
                'url'         => route('probation.show', $employee),
            ],
        ]);
    }
}
