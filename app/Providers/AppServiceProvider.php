<?php

namespace App\Providers;

use App\Mail\Transports\GoogleWebhookTransport;
use App\Services\Ai\Contracts\LlmProviderInterface;
use App\Services\Ai\Providers\GroqLlmProvider;
use App\Services\Ai\Providers\OpenRouterLlmProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LlmProviderInterface::class, function ($app) {
            $provider = strtolower((string) config('services.ai.provider', 'groq'));
            if ($provider === 'openrouter') {
                return new OpenRouterLlmProvider;
            }

            return new GroqLlmProvider;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        TrustProxies::at(config('app.trusted_proxies', []));
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Mail::extend('google_webhook', function (array $config = []) {
            return new GoogleWebhookTransport(
                (string) ($config['endpoint'] ?? $config['url'] ?? config('mail.mailers.google_webhook.endpoint', '')),
                (string) ($config['token'] ?? config('mail.mailers.google_webhook.token', ''))
            );
        });

        // Pagination responsive Zanika (évite les flèches SVG géantes sans Tailwind)
        Paginator::defaultView('vendor.pagination.zanika');
        Paginator::defaultSimpleView('vendor.pagination.zanika');
    }
}
