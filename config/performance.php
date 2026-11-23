<?php

return [
    'slow_request_threshold_ms' => (int) env('PERFORMANCE_SLOW_REQUEST_THRESHOLD_MS', 1000),
    'slow_database_query_threshold_ms' => (int) env('PERFORMANCE_SLOW_DATABASE_QUERY_THRESHOLD_MS', 500),
];
