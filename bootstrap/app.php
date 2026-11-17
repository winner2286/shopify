<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustHosts(at: function () {
            $hosts = array_filter(array_merge(
                [(string) parse_url((string) config('app.url'), PHP_URL_HOST)],
                config('app.trusted_hosts', [])
            ));

            return array_map(fn (string $host) => '^'.preg_quote($host, '/').'$', $hosts);
        }, subdomains: false);
        $middleware->append(SecurityHeaders::class);
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->isAdmin() ? route('admin.dashboard') : route('dashboard'));
        $middleware->preventRequestsDuringMaintenance(except: [
            'admin',
            'admin/*',
            'connexion',
            'deconnexion',
            'up',
            'zanika-admin-bypass',
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/geniuspay/webhook',
            'geniuspay/webhook',
            'geniuspay/callback',
            'api/feexpay/webhook',
            'feexpay/webhook',
            'feexpay/callback',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
