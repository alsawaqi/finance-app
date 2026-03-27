<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

            return $frontendUrl.'/reset-password/'.$token.'?email='.urlencode(
                $notifiable->getEmailForPasswordReset()
            );
        });

        VerifyEmail::createUrlUsing(function (object $notifiable): string {
            $temporarySignedUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(Config::get('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );

            $parsedUrl = parse_url($temporarySignedUrl);
            parse_str($parsedUrl['query'] ?? '', $query);

            $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

            return $frontendUrl.'/verify-email?'.http_build_query([
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
                'expires' => $query['expires'] ?? '',
                'signature' => $query['signature'] ?? '',
            ]);
        });
    }
}