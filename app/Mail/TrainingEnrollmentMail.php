<?php
namespace App\Mail;

use App\Models\TrainingEnrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrainingEnrollmentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TrainingEnrollment $enrollment) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'You Have Been Enrolled: ' . $this->enrollment->course->title);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.training-enrollment');
    }
}
