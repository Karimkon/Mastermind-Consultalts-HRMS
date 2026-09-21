<?php
namespace App\Mail;

use App\Models\Appraisal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The email that goes with an appraisal notification.
 *
 * Every step of an appraisal already wrote an in-app notification and nothing
 * else, so a card could sit with somebody for a week while they had no reason
 * to open the system at all.
 */
class AppraisalNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Appraisal $appraisal,
        public string $heading,
        // NOT $message. Laravel injects its own Illuminate\Mail\Message into
        // every mail view under that name, which shadows the property and makes
        // the view try to print an object.
        public string $intro,
        public ?string $recipientName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->heading);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.appraisal-notification');
    }
}
