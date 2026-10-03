<?php

use App\Modules\Content\Infrastructure\Integrations\YoutubeWebTranscriptFetcher;
use App\Modules\Content\Infrastructure\Integrations\PlaywrightYoutubeTranscriptFetcher;
use App\Modules\Content\Infrastructure\Integrations\LibraryYoutubeTranscriptFetcher;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

test('fetches the requested native YouTube caption track', function () {
    $playerResponse = [
        'captions' => [
            'playerCaptionsTracklistRenderer' => [
                'captionTracks' => [
                    ['languageCode' => 'es', 'baseUrl' => 'https://www.youtube.com/api/timedtext?track=es'],
                    ['languageCode' => 'en', 'baseUrl' => 'https://www.youtube.com/api/timedtext?track=en'],
                ],
            ],
        ],
    ];

    Http::preventStrayRequests();
    Http::fake([
        'https://www.youtube.com/watch*' => Http::response(
            '<script>var ytInitialPlayerResponse = '.json_encode($playerResponse).';</script>',
            200,
        ),
        'https://www.youtube.com/api/timedtext*' => Http::response([
            'events' => [
                ['tStartMs' => 0, 'dDurationMs' => 1200, 'segs' => [['utf8' => 'Hello '], ['utf8' => 'world.']]],
                ['tStartMs' => 1200, 'dDurationMs' => 900, 'segs' => [['utf8' => 'How are you?']]],
            ],
        ], 200),
    ]);

    $document = (new YoutubeWebTranscriptFetcher())->fetch(
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'en',
    );

    expect($document->source)->toBe('youtube-web')
        ->and($document->language)->toBe('en')
        ->and($document->fullText)->toBe('Hello world. How are you?')
        ->and($document->segments)->toHaveCount(2)
        ->and($document->segments[1]->startMs)->toBe(1200);
});

test('fallback driver uses Supadata when YouTube Web has no captions', function () {
    config()->set('transcripts.youtube.driver', 'fallback');
    config()->set('transcripts.youtube.drivers.supadata.api_key', 'test-key');

    Http::preventStrayRequests();
    Http::fake([
        'https://www.youtube.com/watch*' => Http::response(
            '<script>var ytInitialPlayerResponse = '.json_encode(['videoDetails' => []]).';</script>',
            200,
        ),
        'https://api.supadata.ai/v1/transcript*' => Http::response([
            'content' => 'Fallback transcript.',
            'lang' => 'en',
        ], 200),
    ]);

    $document = app(\App\Contracts\YoutubeTranscriptFetcherInterface::class)->fetch(
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'en',
    );

    expect($document->source)->toBe('youtube')
        ->and($document->fullText)->toBe('Fallback transcript.');
});

test('Playwright adapter maps browser worker segments to a transcript document', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://browser:3000/transcript' => Http::response([
            'source' => 'youtube-playwright',
            'language' => 'en',
            'segments' => [
                ['startMs' => 0, 'endMs' => 1000, 'text' => 'Browser transcript.', 'sourceKey' => '0'],
            ],
        ], 200),
    ]);

    $document = (new PlaywrightYoutubeTranscriptFetcher())->fetch(
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'en',
    );

    expect($document->source)->toBe('youtube-playwright')
        ->and($document->fullText)->toBe('Browser transcript.')
        ->and($document->segments[0]->startMs)->toBe(0);
});

test('library adapter maps Node and Python worker segments to a transcript document', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://browser:3000/transcript-node' => Http::response([
            'source' => 'youtube-transcript-node',
            'language' => 'en',
            'segments' => [['startMs' => 100, 'endMs' => 900, 'text' => 'Node transcript.', 'sourceKey' => '0']],
        ], 200),
        'http://transcript-python:3001/transcript' => Http::response([
            'source' => 'youtube-transcript-api-python',
            'language' => 'en',
            'segments' => [['startMs' => 200, 'endMs' => 1000, 'text' => 'Python transcript.', 'sourceKey' => '0']],
        ], 200),
    ]);

    $node = (new LibraryYoutubeTranscriptFetcher('http://browser:3000', '/transcript-node'))->fetch(
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'en',
    );
    $python = (new LibraryYoutubeTranscriptFetcher('http://transcript-python:3001'))->fetch(
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'en',
    );

    expect($node->source)->toBe('youtube-transcript-node')
        ->and($node->fullText)->toBe('Node transcript.')
        ->and($python->source)->toBe('youtube-transcript-api-python')
        ->and($python->segments[0]->startMs)->toBe(200);
});
