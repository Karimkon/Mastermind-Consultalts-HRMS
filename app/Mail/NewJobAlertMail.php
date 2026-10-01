<?php
namespace App\Mail;

use App\Models\JobPosting;
use App\Models\JobSeeker;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** To a job seeker, when a job opens in a category they asked about. */
class NewJobAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public JobSeeker $seeker, public JobPosting $job) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "New vacancy: {$this->job->title}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.new-job-alert');
    }
}
