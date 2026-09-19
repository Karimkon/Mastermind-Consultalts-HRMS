<?php
namespace App\Mail;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent the moment somebody is nominated to cover, not when the leave is
 * finally approved days later.
 *
 * Worded as a nomination throughout: at this point the request is still
 * pending, and telling someone "you are covering" before the client has
 * approved would be telling them something that is not yet true.
 */
class LeaveCoverNominatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public LeaveRequest $leave) {}

    public function envelope(): Envelope
    {
        $name = $this->leave->employee?->full_name ?? 'a colleague';

        return new Envelope(
            subject: "You have been nominated to cover for {$name}"
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.leave-cover-nominated');
    }
}
