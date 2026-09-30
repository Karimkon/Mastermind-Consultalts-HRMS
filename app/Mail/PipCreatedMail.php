<?php
namespace App\Mail;

use App\Models\Pip;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells an employee a performance improvement plan has been opened for them.
 *
 * A PIP has dates attached and consequences if they pass unmet, so it cannot
 * rely on somebody happening to open the system and notice a bell.
 */
class PipCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<string,string> $changes field label => "was X, now Y" */
    public function __construct(public Pip $pip, public array $changes = []) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->changes
            ? 'Your improvement plan has been updated — ' . $this->pip->title
            : 'Performance Improvement Plan — ' . $this->pip->title);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.pip-created');
    }
}
