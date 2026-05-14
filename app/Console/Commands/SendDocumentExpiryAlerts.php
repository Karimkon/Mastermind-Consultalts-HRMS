<?php
namespace App\Console\Commands;

use App\Mail\DocumentExpiryAlertMail;
use App\Models\Employee;
use App\Notifications\DocumentExpiryNotification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendDocumentExpiryAlerts extends Command
{
    protected $signature   = 'hrms:document-expiry-alerts';
    protected $description = 'Send alerts for employee documents and certifications expiring soon';

    public function handle(): int
    {
        $alertDays = [7, 30]; // Alert at 7 days and 30 days
        $today     = now()->startOfDay();
        $horizon   = now()->addDays(30)->endOfDay();

        $sent = 0;

        Employee::with(['user', 'documents', 'certifications'])
            ->where('status', 'active')
            ->chunk(100, function ($employees) use ($today, $horizon, $alertDays, &$sent) {
                foreach ($employees as $employee) {
                    // Expiring documents
                    $expiringDocs  = $employee->documents
                        ->whereNotNull('expiry_date')
                        ->filter(fn($d) => $d->expiry_date >= $today && $d->expiry_date <= $horizon);

                    // Expiring certifications
                    $expiringCerts = $employee->certifications
                        ->whereNotNull('expiry_date')
                        ->filter(fn($c) => $c->expiry_date >= $today && $c->expiry_date <= $horizon);

                    if ($expiringDocs->isEmpty() && $expiringCerts->isEmpty()) {
                        continue;
                    }

                    // Notify HR admins and managers (not the employee directly)
                    $hrUsers = User::role(['hr-admin', 'super-admin'])->get();

                    foreach ($hrUsers as $hrUser) {
                        try {
                            Mail::to($hrUser->email)->send(
                                new DocumentExpiryAlertMail($employee, $expiringDocs, $expiringCerts)
                            );
                            $sent++;
                        } catch (\Exception $e) {
                            $this->warn("Email failed for {$hrUser->email}: " . $e->getMessage());
                            \Log::error('Document expiry email failed: ' . $e->getMessage());
                        }
                    }

                    // Store database notification for HR users
                    foreach ($hrUsers as $hrUser) {
                        try {
                            $hrUser->notify(new DocumentExpiryNotification(
                                $employee,
                                $expiringDocs->count(),
                                $expiringCerts->count()
                            ));
                        } catch (\Exception $e) {
                            \Log::error('Document expiry DB notification failed: ' . $e->getMessage());
                        }
                    }
                }
            });

        $this->info("Document expiry alerts complete. {$sent} emails sent.");
        return Command::SUCCESS;
    }
}
