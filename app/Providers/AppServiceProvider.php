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
        $this->app->bind(\App\Services\Ai\Contracts\LlmProviderInterface::class, function ($app) {
            $provider = strtolower((string) config('services.ai.provider', 'groq'));
            if ($provider === 'openrouter') {
                return new \App\Services\Ai\Providers\OpenRouterLlmProvider();
            }

            return new \App\Services\Ai\Providers\GroqLlmProvider();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production') || ($this->app->bound('request') && request()->header('X-Forwarded-Proto') === 'https')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        \Illuminate\Support\Facades\Mail::extend('google_webhook', function (array $config = []) {
            return new \App\Mail\Transports\GoogleWebhookTransport(
                (string) ($config['endpoint'] ?? $config['url'] ?? config('mail.mailers.google_webhook.endpoint', '')),
                (string) ($config['token'] ?? config('mail.mailers.google_webhook.token', ''))
            );
        });

        // Pagination responsive Zanika (évite les flèches SVG géantes sans Tailwind)
        \Illuminate\Pagination\Paginator::defaultView('vendor.pagination.zanika');
        \Illuminate\Pagination\Paginator::defaultSimpleView('vendor.pagination.zanika');
    }
}
