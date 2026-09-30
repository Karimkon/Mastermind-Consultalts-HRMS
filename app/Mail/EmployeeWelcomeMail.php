<?php
namespace App\Mail;

use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Welcomes a new member of staff and tells them where they sit.
 *
 * Carries the sign-in address but never the password. Email is not a safe place
 * to put one, and this message is forwarded and archived by design - the
 * password is handed over separately, and changed on first sign-in.
 */
class EmployeeWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Employee $employee) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Welcome to Mastermind Consultants');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.employee-welcome', with: [
            'position'   => $this->employee->orgPosition?->title,
            'supervisor' => $this->employee->manager?->full_name,
            'department' => $this->employee->department?->name,
            'loginEmail' => $this->employee->user?->email,
        ]);
    }
}
