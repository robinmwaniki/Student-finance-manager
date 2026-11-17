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
        // Add a safe correlation ID to every response and request log context.
        $middleware->append(RequestCorrelationId::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sentry is inert when SENTRY_LARAVEL_DSN is not configured.
        Integration::handles($exceptions);
    })
    ->create();
