<?php

return [
    'provider' => env('AI_PROVIDER', 'none'),
    'api_key' => env('AI_API_KEY'),
    'model' => env('AI_MODEL', 'gemini-2.5-flash'),
    'base_url' => env('AI_BASE_URL'),
    'timeout' => 8,
    'connect_timeout' => 3,
    'max_question_length' => 1000,
    'max_answer_length' => 2500,
];
