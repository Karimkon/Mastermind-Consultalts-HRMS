<?php
namespace App\Mail;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeaveStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public LeaveRequest $leave) {}

    public function envelope(): Envelope
    {
        $subject = match($this->leave->status) {
            'approved'  => 'Your Leave Request Has Been Approved',
            'rejected'  => 'Your Leave Request Has Been Rejected',
            'cancelled' => 'Your Leave Request Has Been Cancelled',
            default     => 'Leave Request Update',
        };
        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.leave-status');
    }
}
