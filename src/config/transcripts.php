<?php

return [
    'youtube' => [
        // Keep feature tests deterministic even when a developer's local
        // .env selects the browser fallback.
        'driver' => env('APP_ENV') === 'testing' ? 'supadata' : env('YOUTUBE_TRANSCRIPT_DRIVER', 'fallback'),
        'preferred_language' => env('YOUTUBE_TRANSCRIPT_LANG'),
        'drivers' => [
            'stub' => [
                'class' => \App\Modules\Content\Infrastructure\Integrations\StubYoutubeTranscriptFetcher::class,
            ],
            'youtube_web' => [
                'class' => \App\Modules\Content\Infrastructure\Integrations\YoutubeWebTranscriptFetcher::class,
                'base_url' => env('YOUTUBE_WEB_BASE_URL', 'https://www.youtube.com'),
                'timeout' => (int) env('YOUTUBE_WEB_TIMEOUT', 15),
                'user_agent' => env('YOUTUBE_WEB_USER_AGENT', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/131 Safari/537.36'),
            ],
            'youtube_playwright' => [
                'class' => \App\Modules\Content\Infrastructure\Integrations\PlaywrightYoutubeTranscriptFetcher::class,
                'base_url' => env('YOUTUBE_PLAYWRIGHT_BASE_URL', 'http://browser:3000'),
                'timeout' => (int) env('YOUTUBE_PLAYWRIGHT_TIMEOUT', 60),
            ],
            'youtube_transcript_node' => [
                'class' => \App\Modules\Content\Infrastructure\Integrations\LibraryYoutubeTranscriptFetcher::class,
                'base_url' => env('YOUTUBE_TRANSCRIPT_NODE_BASE_URL', 'http://browser:3000'),
                'endpoint' => '/transcript-node',
                'timeout' => (int) env('YOUTUBE_TRANSCRIPT_NODE_TIMEOUT', 45),
            ],
            'youtube_transcript_python' => [
                'class' => \App\Modules\Content\Infrastructure\Integrations\LibraryYoutubeTranscriptFetcher::class,
                'base_url' => env('YOUTUBE_TRANSCRIPT_PYTHON_BASE_URL', 'http://transcript-python:3001'),
                'endpoint' => '/transcript',
                'timeout' => (int) env('YOUTUBE_TRANSCRIPT_PYTHON_TIMEOUT', 45),
            ],
            'supadata' => [
                'class' => \App\Modules\Content\Infrastructure\Integrations\SupadataYoutubeTranscriptFetcher::class,
                'base_url' => env('SUPADATA_BASE_URL', 'https://api.supadata.ai/v1'),
                'api_key' => env('SUPADATA_API_KEY', ''),
                'mode' => env('SUPADATA_TRANSCRIPT_MODE', 'native'),
                'timeout' => (int) env('SUPADATA_TRANSCRIPT_TIMEOUT', 30),
                'poll_interval_ms' => (int) env('SUPADATA_TRANSCRIPT_POLL_INTERVAL_MS', 1000),
                'max_polls' => (int) env('SUPADATA_TRANSCRIPT_MAX_POLLS', 10),
            ],
            'fallback' => [
                'providers' => ['youtube_transcript_node', 'youtube_transcript_python', 'youtube_playwright', 'youtube_web', 'supadata'],
            ],
        ],
    ],
];
