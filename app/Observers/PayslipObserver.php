<?php
namespace App\Observers;

use App\Mail\PayrollProcessedMail;
use App\Models\Payslip;
use Illuminate\Support\Facades\Mail;

class PayslipObserver
{
    public function created(Payslip $payslip): void
    {
        $email = $payslip->employee?->user?->email;
        if ($email) {
            Mail::to($email)->queue(new PayrollProcessedMail($payslip->load(['employee.user','payrollRun'])));
        }
    }
}
