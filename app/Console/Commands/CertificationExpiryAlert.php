<?php
namespace App\Console\Commands;

use App\Mail\CertificationExpiryMail;
use App\Models\Certification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CertificationExpiryAlert extends Command
{
    protected $signature   = 'hrms:cert-expiry-alert';
    protected $description = 'Send expiry alerts for certifications expiring within 30 days';

    public function handle(): int
    {
        $certs = Certification::with(['employee.user'])
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->get();

        $sent = 0;
        foreach ($certs as $cert) {
            $email = $cert->employee?->user?->email;
            if (!$email) continue;
            Mail::to($email)->queue(new CertificationExpiryMail($cert));
            $sent++;
            $this->line("Alert sent to {$email} for '{$cert->name}'");
        }

        $this->info("Done. {$sent} alert(s) sent.");
        return self::SUCCESS;
    }
}
