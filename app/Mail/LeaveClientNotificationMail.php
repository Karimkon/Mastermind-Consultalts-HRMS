<?php
namespace App\Mail;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email sent to the client company (boss) when an employee applies for leave.
 */
class LeaveClientNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public LeaveRequest $leave) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Action Required] Leave Request — ' . $this->leave->employee->full_name
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.leave-client-notification');
    }
}
