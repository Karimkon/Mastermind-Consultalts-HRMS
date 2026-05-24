<?php
namespace App\Console\Commands;

use App\Models\{Client, Employee, Notification, User};
use App\Mail\DocumentExpiryAlertMail;
use App\Mail\DocumentExpiryAmClientMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendDocumentExpiryAlerts extends Command
{
    protected $signature   = 'hrms:document-expiry-alerts';
    protected $description = 'Send 30-day and 7-day expiry alerts for employee documents to HR, Account Managers, and Clients';

    public function handle(): int
    {
        $today = now()->startOfDay();

        // Two alert windows: exactly 30 days out and exactly 7 days out (±1 day buffer for daily runs)
        $windows = [
            30 => [$today->copy()->addDays(29), $today->copy()->addDays(30)],
            7  => [$today->copy()->addDays(6),  $today->copy()->addDays(7)],
        ];

        $emailsSent = 0;
        $inAppSent  = 0;

        // Build a map: employee_id → [client, am_user, client_user]
        $clientMap = [];
        Client::with(['employees', 'accountManager', 'user'])->get()->each(function (Client $client) use (&$clientMap) {
            foreach ($client->employees as $emp) {
                $clientMap[$emp->id][] = [
                    'client'      => $client,
                    'am_user'     => $client->accountManager,
                    'client_user' => $client->user,
                ];
            }
        });

        Employee::with(['user', 'documents', 'certifications', 'department', 'designation'])
            ->where('status', 'active')
            ->chunk(100, function ($employees) use ($windows, $clientMap, &$emailsSent, &$inAppSent) {

                foreach ($employees as $employee) {

                    foreach ($windows as $days => $range) {
                        [$from, $to] = $range;

                        $expiringDocs = $employee->documents
                            ->whereNotNull('expiry_date')
                            ->filter(fn($d) => $d->expiry_date >= $from->toDateString() && $d->expiry_date <= $to->toDateString());

                        $expiringCerts = $employee->certifications
                            ->whereNotNull('expiry_date')
                            ->filter(fn($c) => $c->expiry_date >= $from->toDateString() && $c->expiry_date <= $to->toDateString());

                        if ($expiringDocs->isEmpty() && $expiringCerts->isEmpty()) {
                            continue;
                        }

                        $urgency    = $days === 7 ? 'urgent' : 'warning';
                        $urgencyLabel = $days === 7 ? '🚨 URGENT — 7 Days' : '⚠ Warning — 30 Days';
                        $docCount   = $expiringDocs->count() + $expiringCerts->count();
                        $bodyText   = "{$urgencyLabel}: {$employee->full_name} has {$docCount} document(s) expiring in {$days} days.";

                        // ── 1. Notify HR admins / super-admin ──────────────────
                        $hrUsers = User::role(['hr-admin', 'super-admin'])->get();
                        foreach ($hrUsers as $hrUser) {
                            $this->sendInApp($hrUser->id, $urgency, $employee, $bodyText, $days);
                            $inAppSent++;
                            try {
                                Mail::to($hrUser->email)->queue(
                                    new DocumentExpiryAlertMail($employee, $expiringDocs, $expiringCerts, $days)
                                );
                                $emailsSent++;
                            } catch (\Exception $e) {
                                $this->warn("HR email failed: {$hrUser->email} — " . $e->getMessage());
                            }
                        }

                        // ── 2. Notify Account Manager ──────────────────────────
                        $clientEntries = $clientMap[$employee->id] ?? [];
                        $notifiedAMs   = [];
                        $notifiedClients = [];

                        foreach ($clientEntries as $entry) {
                            $client     = $entry['client'];
                            $amUser     = $entry['am_user'];
                            $clientUser = $entry['client_user'];

                            if ($amUser && !in_array($amUser->id, $notifiedAMs)) {
                                $this->sendInApp($amUser->id, $urgency, $employee, $bodyText, $days);
                                $inAppSent++;
                                try {
                                    Mail::to($amUser->email)->queue(
                                        new DocumentExpiryAmClientMail($employee, $expiringDocs, $expiringCerts, $days, 'am', $client)
                                    );
                                    $emailsSent++;
                                } catch (\Exception $e) {
                                    $this->warn("AM email failed: {$amUser->email} — " . $e->getMessage());
                                }
                                $notifiedAMs[] = $amUser->id;
                                $this->line("  AM notified: {$amUser->email} ({$urgencyLabel})");
                            }

                            // ── 3. Notify Client portal user ──────────────────
                            if ($clientUser && !in_array($clientUser->id, $notifiedClients)) {
                                $this->sendInApp($clientUser->id, $urgency, $employee, $bodyText, $days);
                                $inAppSent++;
                                try {
                                    Mail::to($clientUser->email)->queue(
                                        new DocumentExpiryAmClientMail($employee, $expiringDocs, $expiringCerts, $days, 'client', $client)
                                    );
                                    $emailsSent++;
                                } catch (\Exception $e) {
                                    $this->warn("Client email failed: {$clientUser->email} — " . $e->getMessage());
                                }
                                $notifiedClients[] = $clientUser->id;
                                $this->line("  Client notified: {$clientUser->email} ({$urgencyLabel})");
                            }
                        }

                        $this->line("Processed: {$employee->full_name} — {$days}-day alert, {$docCount} item(s)");
                    }
                }
            });

        $this->info("Done. Emails sent: {$emailsSent} | In-app notifications: {$inAppSent}");
        return Command::SUCCESS;
    }

    private function sendInApp(int $userId, string $urgency, Employee $employee, string $body, int $days): void
    {
        $title = $urgency === 'urgent'
            ? "🚨 Urgent: {$employee->full_name}'s documents expire in {$days} days"
            : "⚠ Alert: {$employee->full_name}'s documents expire in {$days} days";

        Notification::create([
            'user_id' => $userId,
            'type'    => 'document_expiry_' . $urgency,
            'title'   => $title,
            'body'    => $body,
            'data'    => ['employee_id' => $employee->id, 'days' => $days],
        ]);
    }
}
