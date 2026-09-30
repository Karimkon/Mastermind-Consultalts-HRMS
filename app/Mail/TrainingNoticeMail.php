<?php
namespace App\Mail;

use App\Models\TrainingSession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One mailable for every step of the training plan.
 *
 * The chain produces six different messages - nominated, awaiting HR, awaiting
 * CEO, approved, booked, sent back - and they differ only in their heading and
 * a sentence. Six near-identical classes would drift apart; the session detail
 * table beneath is the same in all of them.
 */
class TrainingNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TrainingSession $session,
        public string $heading,
        public string $intro,
        public string $subjectLine
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.training-notice');
    }
}
