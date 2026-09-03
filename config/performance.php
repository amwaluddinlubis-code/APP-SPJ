<?php

return [
    'enabled' => env('PERFORMANCE_MONITORING', true),
    'slow_request_ms' => (int) env('PERFORMANCE_SLOW_REQUEST_MS', 1000),
    'slow_query_ms' => (int) env('PERFORMANCE_SLOW_QUERY_MS', 200),
];
