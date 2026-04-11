<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class AuthVerifyEmailNotification extends BaseVerifyEmail
{
    use Queueable;

    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationFrontendUrl($notifiable);

        $mail = (new MailMessage)
            ->mailer((string) config('mail.auth_mailer', 'auth_smtp'))
            ->subject('Verify Your Email Address')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Please verify your email address to complete your account setup.')
            ->action('Verify Email', $verificationUrl)
            ->line('If you did not create an account, no further action is required.');

        $fromAddress = config('mail.auth_from.address');
        $fromName = config('mail.auth_from.name');

        if ($fromAddress) {
            $mail->from((string) $fromAddress, $fromName ? (string) $fromName : null);
        }

        return $mail;
    }

    protected function verificationFrontendUrl($notifiable): string
    {
        $backendUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes((int) Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        $parts = parse_url($backendUrl);
        parse_str($parts['query'] ?? '', $query);

        $frontendBase = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return $frontendBase.'/verify-email?'.http_build_query([
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
            'expires' => $query['expires'] ?? null,
            'signature' => $query['signature'] ?? null,
        ]);
    }
}