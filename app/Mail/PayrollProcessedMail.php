<?php
namespace App\Mail;

use App\Models\Payslip;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PayrollProcessedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Payslip $payslip) {}

    public function envelope(): Envelope
    {
        $run = $this->payslip->payrollRun;
        return new Envelope(subject: 'Your Payslip is Ready — ' . ($run?->title ?? 'Payroll'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.payroll-processed');
    }
}
