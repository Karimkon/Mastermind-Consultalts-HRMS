<?php
namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

class ResetPasswordNotification extends BaseResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url    = $this->resetUrl($notifiable);
        $expiry = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('Reset Your HRMS Password')
            ->view('mail.password-reset', [
                'url'        => $url,
                'expiry'     => $expiry,
                'notifiable' => $notifiable,
            ]);
    }
}
