<?php
namespace App\Mail;

use App\Models\EmployeeGoal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells an employee a goal has been set for them.
 *
 * A goal carries a weight and a target date and is scored later, so being told
 * it exists is the whole point - goals were being assigned silently.
 */
class GoalAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<string,string> $changes field label => "was X, now Y" */
    public function __construct(public EmployeeGoal $goal, public array $changes = []) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->changes
            ? 'Your goal has been updated — ' . $this->goal->title
            : 'A new goal has been set for you — ' . $this->goal->title);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.goal-assigned');
    }
}
