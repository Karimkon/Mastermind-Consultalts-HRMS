<?php
namespace App\Mail;

use App\Models\Candidate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class JobApplicationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Candidate $candidate) {}

    public function envelope(): Envelope
    {
        $job = $this->candidate->jobPosting?->title ?? 'a position';
        return new Envelope(subject: "New Application: {$this->candidate->first_name} {$this->candidate->last_name} — {$job}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.job-application');
    }
}
