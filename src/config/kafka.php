<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('KAFKA_ENABLED', false),
    'brokers' => env('KAFKA_BROKERS', 'localhost:9092'),
    'client_id' => env('KAFKA_CLIENT_ID', 'laravel-learning'),
    'consumer_group' => env('KAFKA_CONSUMER_GROUP', 'laravel-learning-consumer'),
];
