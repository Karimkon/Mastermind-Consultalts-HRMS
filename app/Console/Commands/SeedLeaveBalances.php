<?php
namespace App\Console\Commands;

use App\Models\{Employee, LeaveBalance, LeaveType};
use Illuminate\Console\Command;

class SeedLeaveBalances extends Command
{
    protected $signature   = 'hrms:seed-leave-balances {year? : The year to seed (defaults to current year)}';
    protected $description = 'Seed leave balances for all active employees, applying carry-forward from previous year';

    public function handle(): int
    {
        $year      = (int) ($this->argument('year') ?? now()->year);
        $prevYear  = $year - 1;
        $employees = Employee::where('status', 'active')->orWhere('status', 'on_leave')->get();
        $types     = LeaveType::where('is_active', true)->get();

        $created = 0;
        $this->info("Seeding leave balances for {$year} ({$employees->count()} employees, {$types->count()} types)...");

        foreach ($employees as $emp) {
            foreach ($types as $type) {
                // Check if balance already exists
                $exists = LeaveBalance::where('employee_id', $emp->id)
                    ->where('leave_type_id', $type->id)
                    ->where('year', $year)->exists();

                if ($exists) continue;

                // Carry-forward calculation
                $carryOver = 0;
                if ($type->carry_forward) {
                    $prev = LeaveBalance::where('employee_id', $emp->id)
                        ->where('leave_type_id', $type->id)
                        ->where('year', $prevYear)->first();
                    if ($prev) {
                        $remaining = max(0, $prev->total_days - $prev->used_days - $prev->pending_days);
                        $carryOver = min($remaining, $type->max_carry_forward ?? 0);
                    }
                }

                LeaveBalance::create([
                    'employee_id'   => $emp->id,
                    'leave_type_id' => $type->id,
                    'year'          => $year,
                    'total_days'    => $type->days_allowed + $carryOver,
                    'used_days'     => 0,
                    'pending_days'  => 0,
                ]);
                $created++;
            }
        }

        $this->info("Done. Created {$created} leave balance records.");
        return self::SUCCESS;
    }
}
