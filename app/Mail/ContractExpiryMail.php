<?php
namespace App\Mail;

use App\Models\{Employee, Client};
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContractExpiryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Employee $employee,
        public int      $days,
        public string   $urgency,
        public ?Client  $client = null
    ) {}

    public function envelope(): Envelope
    {
        $prefix = $this->urgency === 'urgent' ? '[URGENT]' : '[Notice]';
        return new Envelope(
            subject: "{$prefix} Contract Expiring in {$this->days} Days — {$this->employee->full_name}"
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.contract-expiry');
    }
}
