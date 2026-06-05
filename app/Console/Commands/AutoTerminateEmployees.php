<?php
namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;

class AutoTerminateEmployees extends Command
{
    protected $signature   = 'hrms:auto-terminate-employees';
    protected $description = 'Auto-terminate employees whose end_date or contract_end_date has passed';

    public function handle(): int
    {
        $today = now()->toDateString();
        $terminated = 0;
        $contractExpired = 0;

        // End date reached → terminated
        $byEndDate = Employee::whereIn('status', ['active', 'on_leave'])
            ->whereNotNull('end_date')
            ->where('end_date', '<', $today)
            ->get();

        foreach ($byEndDate as $emp) {
            $emp->update(['status' => 'terminated']);
            // Cancel all future leaves
            $emp->leaveRequests()->where('status', 'pending')->update(['status' => 'cancelled']);
            $emp->leaveRequests()->where('status', 'approved')->where('from_date', '>', $today)
                ->update(['status' => 'cancelled', 'rejection_reason' => 'Auto-terminated — employment end date reached.']);
            $this->line("Terminated: {$emp->full_name} (end_date: {$emp->end_date})");
            $terminated++;
        }

        // Contract end date reached → contract_expired
        $byContract = Employee::whereIn('status', ['active', 'on_leave'])
            ->whereNotNull('contract_end_date')
            ->where('contract_end_date', '<', $today)
            ->get();

        foreach ($byContract as $emp) {
            $emp->update(['status' => 'contract_expired']);
            $emp->leaveRequests()->where('status', 'pending')->update(['status' => 'cancelled']);
            $emp->leaveRequests()->where('status', 'approved')->where('from_date', '>', $today)
                ->update(['status' => 'cancelled', 'rejection_reason' => 'Auto-expired — employment contract ended.']);
            $this->line("Contract expired: {$emp->full_name} (contract_end_date: {$emp->contract_end_date})");
            $contractExpired++;
        }

        $this->info("Done. {$terminated} terminated, {$contractExpired} contract expired.");
        return self::SUCCESS;
    }
}
