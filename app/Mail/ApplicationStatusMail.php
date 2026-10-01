<?php
namespace App\Mail;

use App\Models\Candidate;
use App\Models\CandidateStatusEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * To the applicant, when their application moves.
 *
 * The subject names the stage, because somebody who has applied for four
 * jobs needs to tell these apart in a crowded inbox.
 */
class ApplicationStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Candidate $candidate,
        public CandidateStatusEvent $event,
        public array $progress = [],
    ) {}

    public function envelope(): Envelope
    {
        $job     = $this->candidate->jobPosting?->title ?? 'your application';
        $subject = match ($this->event->to_status) {
            'new'         => "Application received: {$job}",
            'screening'   => "Your application is being screened: {$job}",
            'shortlisted' => "You have been shortlisted: {$job}",
            'interview'   => "Interview invitation: {$job}",
            'offer'       => "Job offer: {$job}",
            'hired'       => "Welcome aboard: {$job}",
            'rejected'    => "Update on your application: {$job}",
            default       => "Update on your application: {$job}",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.application-status');
    }
}
