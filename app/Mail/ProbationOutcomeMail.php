<?php
namespace App\Mail;

use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells an employee the outcome of their probation.
 *
 * One mailable covers all three outcomes because they are the same letter with
 * a different verdict: sending a confirmation is good news, an extension is a
 * date and a reason, and a failure is a decision the person is owed plainly.
 */
class ProbationOutcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Employee $employee,
        public string   $outcome,
        public ?string  $notes = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->outcome) {
            'passed'   => 'Congratulations — your employment has been confirmed',
            'extended' => 'Your probation period has been extended',
            'failed'   => 'Outcome of your probation review',
            default    => 'Probation review outcome',
        });
    }

    public function content(): Content
    {
        return new Content(view: 'mail.probation-outcome', with: [
            'heading' => match ($this->outcome) {
                'passed'   => 'Your employment is confirmed',
                'extended' => 'Your probation has been extended',
                'failed'   => 'Outcome of your probation review',
                default    => 'Probation review outcome',
            },
            'intro' => match ($this->outcome) {
                'passed' => 'Congratulations. Your probation period is complete and your '
                          . 'employment with Mastermind Consult has been confirmed. Thank you '
                          . 'for the work you have put in so far - we are glad to have you with us.',
                'extended' => 'Following your probation review, your probation period has been '
                            . 'extended. The new end date is shown below. Your manager will go '
                            . 'through what is expected between now and then.',
                'failed' => 'Following your probation review, a decision has been taken not to '
                          . 'confirm your employment at the end of your probation period. Your '
                          . 'manager or HR will be in touch to talk this through with you.',
                default => 'Your probation review has been completed.',
            },
        ]);
    }
}
