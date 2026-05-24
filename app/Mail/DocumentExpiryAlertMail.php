<?php
namespace App\Mail;

use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class DocumentExpiryAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public Employee   $employee;
    public Collection $documents;
    public Collection $certifications;

    public int $days;

    public function __construct(Employee $employee, $documents, $certifications, int $days = 30)
    {
        $this->employee       = $employee;
        $this->documents      = collect($documents);
        $this->certifications = collect($certifications);
        $this->days           = $days;
    }

    public function envelope(): Envelope
    {
        $prefix = $this->days <= 7 ? '🚨 URGENT' : '⚠ Action Required';
        return new Envelope(
            subject: "{$prefix}: Employee Documents Expiring in {$this->days} Days — {$this->employee->full_name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.document-expiry-alert',
            with: [
                'employee'       => $this->employee,
                'documents'      => $this->documents,
                'certifications' => $this->certifications,
                'days'           => $this->days,
            ]
        );
    }
}
