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

    public function __construct(Employee $employee, $documents, $certifications)
    {
        $this->employee       = $employee;
        $this->documents      = collect($documents);
        $this->certifications = collect($certifications);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Action Required: Employee Documents Expiring Soon — ' . $this->employee->full_name,
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
            ]
        );
    }
}
