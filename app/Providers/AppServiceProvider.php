<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(
            function ($notifiable, string $token) {

                $frontendUrl = rtrim(
                    env('FRONTEND_URL', 'http://127.0.0.1:5173'),
                    '/'
                );

                $email = $notifiable->getEmailForPasswordReset();

                return $frontendUrl
                    . '/reset-password'
                    . '?token=' . urlencode($token)
                    . '&email=' . urlencode($email);
            }
        );
    }
}