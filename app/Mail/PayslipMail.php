<?php
namespace App\Mail;

use App\Models\{Payslip, PayrollRun};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope, Attachment};
use Illuminate\Queue\SerializesModels;

class PayslipMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Payslip $payslip, public PayrollRun $run) {}

    public function envelope(): Envelope
    {
        $month = \Carbon\Carbon::createFromDate($this->run->year, $this->run->month, 1)->format('F Y');

        // Sent from the authenticated SMTP account, but replies go to payroll.
        // Overriding the From address instead would be rejected by the mail
        // provider, since it only permits its own verified sender.
        return new Envelope(
            subject: "Your Payslip — {$month}",
            replyTo: [new \Illuminate\Mail\Mailables\Address(
                \App\Models\Setting::get('company_email', 'payroll@mastermindconsults.co.ug'),
                'Mastermind Consult Ltd Payroll'
            )],
        );
    }

    public function content(): Content
    {
        // The view refers to the run as $payroll_run, matching the payslip PDF
        // template; the Mailable property is $run, so bind it explicitly.
        return new Content(view: 'emails.payslip', with: [
            'payroll_run' => $this->run,
        ]);
    }

    public function attachments(): array
    {
        $employee = $this->payslip->employee;
        $company  = [
            'name'     => \App\Models\Setting::get('company_name', 'Mastermind Consult Ltd'),
            'email'    => \App\Models\Setting::get('company_email', ''),
            'phone'    => \App\Models\Setting::get('company_phone', '+256 393 215 289'),
            'currency' => \App\Models\Setting::get('currency_symbol', 'UGX'),
        ];
        $payslip     = $this->payslip;
        $payroll_run = $this->run;
        $payroll_run->loadMissing('client');
        $logoPath = public_path('images/logo.png');
        $logo     = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;

        $avatar = null;
        $avatarPath = $this->payslip->employee->user?->avatar;
        if ($avatarPath) {
            $fullPath = storage_path('app/public/' . $avatarPath);
            if (file_exists($fullPath)) {
                $mime   = mime_content_type($fullPath);
                $avatar = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($fullPath));
            }
        }

        $pdf = Pdf::loadView('payroll.payslip-pdf', compact('payslip', 'payroll_run', 'company', 'logo', 'avatar'))
            ->setPaper('a4');

        $filename = "payslip-{$employee->emp_number}-{$this->run->month}-{$this->run->year}.pdf";

        return [
            Attachment::fromData(fn () => $pdf->output(), $filename)
                ->withMime('application/pdf'),
        ];
    }
}
