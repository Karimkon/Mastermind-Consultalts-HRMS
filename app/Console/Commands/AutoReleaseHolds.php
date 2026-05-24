<?php
namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Notification;
use Illuminate\Console\Command;

class AutoReleaseHolds extends Command
{
    protected $signature   = 'hrms:auto-release-holds';
    protected $description = 'Automatically release employee holds whose end date has passed';

    public function handle(): void
    {
        $expired = Employee::where('on_hold', true)
            ->whereNotNull('hold_end_date')
            ->where('hold_end_date', '<', now()->toDateString())
            ->get();

        foreach ($expired as $employee) {
            $employee->update([
                'on_hold'      => false,
                'hold_end_date' => null,
            ]);

            // In-app notification to HR admins
            $hrAdmins = \App\Models\User::role('hr-admin')->get();
            foreach ($hrAdmins as $admin) {
                Notification::create([
                    'user_id' => $admin->id,
                    'title'   => 'Hold Released: ' . $employee->full_name,
                    'body'    => $employee->full_name . ' (' . $employee->emp_number . ') has been automatically released from hold as of ' . now()->format('d M Y') . '.',
                    'type'    => 'info',
                    'link'    => route('employees.show', $employee),
                ]);
            }

            $this->line("Released hold for: {$employee->full_name} ({$employee->emp_number})");
        }

        $this->info("Auto-released {$expired->count()} employee hold(s).");
    }
}
