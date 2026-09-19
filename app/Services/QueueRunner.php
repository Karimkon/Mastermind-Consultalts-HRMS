<?php
namespace App\Services;

/**
 * Nudges the queue so mail sent from the app goes out straight away.
 *
 * Mail is queued rather than sent inline because a payroll run of several hundred
 * employees is that many PDF renders and SMTP round-trips. On this host there is
 * no persistent queue worker — draining is done by short runs of queue-run.sh from
 * a cron job. That leaves up to a minute of delay, and none at all if the cron job
 * is ever removed, which is how a backlog of unsent payslip notices built up before.
 *
 * Launching the drain script detached right after queueing closes that gap. The
 * cron job stays as the safety net for anything queued elsewhere, and the lock
 * inside the script means calling both is harmless.
 */
class QueueRunner
{
    public static function kick(): bool
    {
        $script = base_path('queue-run.sh');

        // proc_open is the only process launcher left enabled on this host;
        // exec/shell_exec/popen are all in disable_functions.
        if (!is_file($script) || !function_exists('proc_open')) {
            return false;
        }

        try {
            $descriptors = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
            $handle = @proc_open(
                '/bin/sh ' . escapeshellarg($script) . ' > /dev/null 2>&1 &',
                $descriptors,
                $pipes
            );

            if (!is_resource($handle)) {
                return false;
            }

            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) @fclose($pipe);
            }
            // The trailing & means the shell has already forked and returned,
            // so this closes immediately rather than waiting on the worker.
            @proc_close($handle);

            return true;
        } catch (\Throwable $e) {
            // A failed nudge is not worth failing the request over — the cron
            // job will pick the work up on its next pass.
            report($e);
            return false;
        }
    }
}
