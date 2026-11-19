<?php

use App\Http\Middleware\RequestCorrelationId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust the Render reverse proxy so Laravel detects HTTPS and the public host.
        // The container is exposed through Render's managed proxy, not directly to clients.
        $middleware->trustProxies(at: '*');

        // Add a safe correlation ID to every response and request log context.
        $middleware->append(RequestCorrelationId::class);
        $middleware->validateCsrfTokens(except: ['api/expense-capture/mpesa-webhook']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sentry is inert when SENTRY_LARAVEL_DSN is not configured.
        Integration::handles($exceptions);
    })
    ->create();
