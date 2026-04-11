<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AuthResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $token
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $resetUrl = $frontendUrl.'/reset-password/'.$this->token.'?email='.urlencode($notifiable->getEmailForPasswordReset());

        $mail = (new MailMessage)
            ->mailer((string) config('mail.auth_mailer', 'auth_smtp'))
            ->subject('Reset Your Password')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('We received a request to reset your password.')
            ->action('Reset Password', $resetUrl)
            ->line('If you did not request a password reset, no further action is required.');

        $fromAddress = config('mail.auth_from.address');
        $fromName = config('mail.auth_from.name');

        if ($fromAddress) {
            $mail->from((string) $fromAddress, $fromName ? (string) $fromName : null);
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}