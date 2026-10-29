<?php

namespace App\Providers;

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
        if ($this->app->environment('production') || request()->header('X-Forwarded-Proto') === 'https') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        \Illuminate\Support\Facades\Mail::extend('google_webhook', function (array $config = []) {
            return new \App\Mail\Transports\GoogleWebhookTransport(
                $config['url'] ?? (string) env('GOOGLE_MAIL_WEBHOOK_URL'),
                $config['token'] ?? (string) env('GOOGLE_MAIL_WEBHOOK_TOKEN', 'ZanikaSecureMailToken2026')
            );
        });
    }
}
