<?php
namespace App\Mail;

use App\Models\PayrollRun;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the next approver in the payroll chain that a run is sitting on their desk.
 *
 * The chain used to hand over in silence: a run went to "processed" and simply
 * waited, because the only way to discover it was to open the payroll list and
 * look. Eighteen runs sat that way. Payroll has a pay date attached, so the
 * handover cannot depend on somebody happening to check.
 */
class PayrollStageMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param string      $heading  what has happened, in the approver's terms
     * @param string      $action   what they now have to do
     * @param string|null $comment  the sender's reason, when a run is sent back
     */
    public function __construct(
        public PayrollRun $run,
        public string $heading,
        public string $action,
        public ?string $comment = null,
        public ?string $fromName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->heading . ' — ' . $this->run->title);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.payroll-stage');
    }
}
