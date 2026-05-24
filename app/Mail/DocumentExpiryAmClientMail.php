<?php
namespace App\Mail;

use App\Models\{Client, Employee};
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class DocumentExpiryAmClientMail extends Mailable
{
    use Queueable, SerializesModels;

    public Collection $documents;
    public Collection $certifications;

    public function __construct(
        public Employee $employee,
        $documents,
        $certifications,
        public int $days,
        public string $recipientType, // 'am' or 'client'
        public Client $client,
    ) {
        $this->documents      = collect($documents);
        $this->certifications = collect($certifications);
    }

    public function envelope(): Envelope
    {
        $prefix = $this->days <= 7 ? '🚨 URGENT' : '⚠ Alert';
        return new Envelope(
            subject: "{$prefix}: {$this->employee->full_name}'s Documents Expire in {$this->days} Days — {$this->client->company_name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.document-expiry-am-client',
            with: [
                'employee'       => $this->employee,
                'documents'      => $this->documents,
                'certifications' => $this->certifications,
                'days'           => $this->days,
                'recipientType'  => $this->recipientType,
                'client'         => $this->client,
            ]
        );
    }
}
