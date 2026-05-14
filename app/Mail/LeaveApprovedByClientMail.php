<?php
namespace App\Mail;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email sent to employee when client approves their leave request.
 */
class LeaveApprovedByClientMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public LeaveRequest $leave) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Leave Request Has Been Approved'
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.leave-approved-by-client');
    }
}
