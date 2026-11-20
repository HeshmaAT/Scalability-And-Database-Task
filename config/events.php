<?php
return [
    'enabled' => env('CLICK_STREAM_ENABLED', false),
    'stream' => 'events:links:v1',
    'maxlen' => 1200000,
    'host' => env('STREAM_REDIS_HOST', 'redis-streams'),
    'port' => (int) env('STREAM_REDIS_PORT', 6379),
];
