<?php
namespace App\Mail;

use App\Models\Certification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CertificationExpiryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Certification $certification) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Certification Expiring Soon: ' . $this->certification->name);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.certification-expiry');
    }
}
