<?php

return [
    'source_connection' => env('LEGACY_IMPORT_SOURCE_CONNECTION', 'legacy_mysql'),
    'target_connection' => env('LEGACY_IMPORT_TARGET_CONNECTION', 'pgsql'),

    // Import is intentionally available only in a dedicated staging environment.
    'allowed_environment' => 'staging',

    // Comma-separated exact database names. Never use a wildcard.
    'allowed_target_databases' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('LEGACY_IMPORT_ALLOWED_TARGET_DATABASES', ''))
    ))),

    'batch_size' => 500,
];
