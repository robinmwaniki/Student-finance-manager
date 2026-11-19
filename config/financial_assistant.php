<?php

return [
    'provider' => env('FINANCIAL_ASSISTANT_PROVIDER', 'gemini'),
    'api_key' => env('FINANCIAL_ASSISTANT_API_KEY'),
    'model' => env('FINANCIAL_ASSISTANT_MODEL', 'gemini-2.5-flash'),
    'base_url' => env('FINANCIAL_ASSISTANT_BASE_URL'),
    'timeout' => (int) env('FINANCIAL_ASSISTANT_TIMEOUT', 20),
    'connect_timeout' => (int) env('FINANCIAL_ASSISTANT_CONNECT_TIMEOUT', 5),
    'max_output_chars' => (int) env('FINANCIAL_ASSISTANT_MAX_OUTPUT_CHARS', 5000),
];
