<?php

use App\Support\Observability\RedactSentryEvent;

return [
    // An empty DSN disables delivery; never commit a project-specific DSN.
    'dsn' => env('SENTRY_LARAVEL_DSN'),

    'release' => env('SENTRY_RELEASE'),
    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV')),
    'send_default_pii' => false,

    // Keep only operational breadcrumbs. SQL and HTTP payload metadata may expose finance data.
    'breadcrumbs' => [
        'logs' => true,
        'cache' => false,
        'sql_queries' => false,
        'sql_bindings' => false,
        'queue_info' => false,
        'command_info' => false,
        'http_client_requests' => false,
        'notifications' => false,
    ],

    'tracing' => [
        'queue_job_transactions' => false,
        'queue_jobs' => false,
        'sql_queries' => false,
        'cache' => false,
        'redis_commands' => false,
        'notifications' => false,
        'default_integrations' => true,
    ],

    // Use a static callable so Laravel's config cache can safely serialize this file.
    'before_send' => [RedactSentryEvent::class, 'handle'],
];
