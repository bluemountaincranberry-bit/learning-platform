<?php declare(strict_types=1);

$host = env('ELASTICSEARCH_HOST');
$port = env('ELASTICSEARCH_PORT', '9200');
$elasticHost = ($host !== null && $host !== '')
    ? sprintf('http://%s:%s', $host, $port)
    : env('ELASTIC_HOST', 'http://localhost:9200');

return [
    'default' => env('ELASTIC_CONNECTION', 'default'),
    'connections' => [
        'default' => [
            'hosts' => [$elasticHost],
        ],
    ],
];
