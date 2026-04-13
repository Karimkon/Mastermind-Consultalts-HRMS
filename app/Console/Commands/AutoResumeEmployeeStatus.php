<?php
namespace App\Console\Commands;

use App\Models\{Employee, LeaveRequest};
use Illuminate\Console\Command;

class AutoResumeEmployeeStatus extends Command
{
    protected $signature   = 'hrms:auto-resume-leave-status';
    protected $description = 'Set employees back to active when their approved leave has ended';

    public function handle(): int
    {
        // Find employees whose approved leave ended before today
        $onLeave = Employee::where('status', 'on_leave')->get();
        $resumed = 0;

        foreach ($onLeave as $emp) {
            $hasActiveLeave = LeaveRequest::where('employee_id', $emp->id)
                ->where('status', 'approved')
                ->where('from_date', '<=', today())
                ->where('to_date', '>=', today())
                ->exists();

            if (!$hasActiveLeave) {
                $emp->update(['status' => 'active']);
                $resumed++;
                $this->line("Resumed: {$emp->full_name}");
            }
        }

        $this->info("Done. {$resumed} employee(s) set back to active.");
        return self::SUCCESS;
    }
}
