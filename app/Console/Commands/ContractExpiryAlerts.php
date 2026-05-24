<?php
namespace App\Console\Commands;

use App\Models\{Client, Employee, Notification, User};
use App\Mail\ContractExpiryMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class ContractExpiryAlerts extends Command
{
    protected $signature   = 'hrms:contract-expiry-alerts';
    protected $description = 'Send 30-day and 15-day contract expiry alerts to Account Managers and HR';

    public function handle(): int
    {
        $today = now()->startOfDay();

        // Alert windows: 30 days out and 15 days out (±1 day buffer for daily runs)
        $windows = [
            30 => [$today->copy()->addDays(29), $today->copy()->addDays(30)],
            15 => [$today->copy()->addDays(14), $today->copy()->addDays(15)],
        ];

        $emailsSent = 0;
        $inAppSent  = 0;

        // Build employee → client/AM map
        $clientMap = [];
        Client::with(['employees', 'accountManager'])->get()->each(function (Client $client) use (&$clientMap) {
            foreach ($client->employees as $emp) {
                $clientMap[$emp->id][] = [
                    'client'  => $client,
                    'am_user' => $client->accountManager,
                ];
            }
        });

        // Find employees with contract_applicable = true and an end_date set
        Employee::with(['user', 'department', 'designation'])
            ->where('contract_applicable', true)
            ->whereNotNull('end_date')
            ->where('status', 'active')
            ->chunk(100, function ($employees) use ($windows, $clientMap, &$emailsSent, &$inAppSent) {

                foreach ($employees as $employee) {
                    foreach ($windows as $days => [$from, $to]) {

                        $endDate = $employee->end_date;
                        if (!($endDate >= $from && $endDate <= $to)) continue;

                        $daysLeft    = now()->diffInDays($endDate);
                        $urgency     = $days === 15 ? 'urgent' : 'warning';
                        $urgencyLabel = $days === 15 ? 'URGENT — 15 Days' : 'Warning — 30 Days';

                        $this->line("  Contract expiring [{$urgencyLabel}]: {$employee->full_name} — {$endDate->format('d M Y')}");

                        $clientEntries = $clientMap[$employee->id] ?? [];
                        $notifiedAMs   = [];

                        // Notify Account Manager(s)
                        foreach ($clientEntries as $entry) {
                            $amUser = $entry['am_user'];
                            if ($amUser && !in_array($amUser->id, $notifiedAMs)) {
                                $this->sendInApp($amUser->id, $urgency, $employee, $daysLeft, $entry['client']);
                                $inAppSent++;
                                try {
                                    Mail::to($amUser->email)->send(
                                        new ContractExpiryMail($employee, $days, $urgency, $entry['client'])
                                    );
                                    $emailsSent++;
                                } catch (\Exception $e) {
                                    $this->warn("AM email failed: {$amUser->email} — " . $e->getMessage());
                                }
                                $notifiedAMs[] = $amUser->id;
                            }
                        }

                        // Notify HR Admins
                        $hrUsers = User::role(['hr-admin', 'super-admin'])->get();
                        foreach ($hrUsers as $hrUser) {
                            $this->sendInApp($hrUser->id, $urgency, $employee, $daysLeft, null);
                            $inAppSent++;
                            try {
                                Mail::to($hrUser->email)->send(
                                    new ContractExpiryMail($employee, $days, $urgency, $clientEntries[0]['client'] ?? null)
                                );
                                $emailsSent++;
                            } catch (\Exception $e) {
                                $this->warn("HR email failed: {$hrUser->email} — " . $e->getMessage());
                            }
                        }
                    }
                }
            });

        $this->info("Contract expiry alerts done. Emails: {$emailsSent} | In-app: {$inAppSent}");
        return Command::SUCCESS;
    }

    private function sendInApp(int $userId, string $urgency, Employee $employee, int $daysLeft, ?Client $client): void
    {
        $prefix = $urgency === 'urgent' ? 'URGENT' : 'Notice';
        Notification::create([
            'user_id' => $userId,
            'type'    => 'contract_expiry_' . $urgency,
            'title'   => "{$prefix}: {$employee->full_name}'s contract expires in {$daysLeft} day(s)",
            'body'    => "Contract end date: {$employee->end_date->format('d M Y')}" .
                         ($client ? " | Client: {$client->company_name}" : ''),
            'data'    => ['employee_id' => $employee->id, 'days_left' => $daysLeft],
        ]);
    }
}
