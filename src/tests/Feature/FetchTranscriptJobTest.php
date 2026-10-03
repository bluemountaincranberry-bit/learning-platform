<?php

use App\Modules\Content\Interfaces\Jobs\FetchTranscriptJob;
use App\Modules\Content\Domain\Models\Content;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('fetch transcript job fills source_text and dispatches process content job', function () {
    config()->set('transcripts.youtube.driver', 'supadata');
    config()->set('transcripts.youtube.drivers.supadata.api_key', 'test-key');

    Http::preventStrayRequests();
    Http::fake([
        'https://api.supadata.ai/v1/transcript*' => Http::response([
            'content' => 'Real transcript from provider.',
            'lang' => 'en',
            'availableLangs' => ['en'],
        ], 200),
    ]);

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Test Video',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'pending',
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'source_text' => null,
    ]);

    (new FetchTranscriptJob($content->id))->handle(app(\App\Contracts\YoutubeTranscriptFetcherInterface::class));

    $content->refresh();
    expect($content->source_text)->not->toBeEmpty();
    expect($content->source_text)->toBe('Real transcript from provider.');
    expect($content->status)->toBe('ready');
    expect($content->processing_failure_reason)->toBeNull();
    expect($content->processing_completed_at)->not->toBeNull();
    // Lexeme extraction belongs to the asynchronous AI analysis layer.
    expect($content->lexemes()->count())->toBe(0);
});

test('fetch transcript job is idempotent when retried', function () {
    config()->set('transcripts.youtube.driver', 'supadata');
    config()->set('transcripts.youtube.drivers.supadata.api_key', 'test-key');

    Http::preventStrayRequests();
    Http::fake([
        'https://api.supadata.ai/v1/transcript*' => Http::response([
            'content' => 'Idempotent transcript.',
            'lang' => 'en',
            'availableLangs' => ['en'],
        ], 200),
    ]);

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Retry video',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'pending',
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'source_text' => null,
    ]);

    $job = new FetchTranscriptJob($content->id);
    $job->handle(app(\App\Contracts\YoutubeTranscriptFetcherInterface::class));
    $job->handle(app(\App\Contracts\YoutubeTranscriptFetcherInterface::class));

    $content->refresh();
    expect($content->status)->toBe('ready');
    expect($content->processing_failure_reason)->toBeNull();
    expect($content->lexemes()->count())->toBe(0);
});

test('fetch transcript job sets status failed when transcript fetch throws', function () {
    config()->set('transcripts.youtube.driver', 'supadata');
    config()->set('transcripts.youtube.drivers.supadata.api_key', 'test-key');

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'pending',
        'source_url' => 'https://invalid-no-video-id',
        'source_text' => null,
    ]);

    expect(fn () => (new FetchTranscriptJob($content->id))->handle(app(\App\Contracts\YoutubeTranscriptFetcherInterface::class)))
        ->toThrow(\App\Exceptions\InvalidYoutubeUrlException::class);

    $content->refresh();
    expect($content->status)->toBe('failed');
    expect($content->processing_failure_reason)->toBe('Invalid YouTube URL: cannot extract video ID.');
    expect($content->processing_completed_at)->not->toBeNull();
});

test('fetch transcript job does nothing when content status is not pending or processing', function () {
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'draft',
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'source_text' => null,
    ]);

    (new FetchTranscriptJob($content->id))->handle(app(\App\Contracts\YoutubeTranscriptFetcherInterface::class));

    $content->refresh();
    expect($content->source_text)->toBeNull();
    expect($content->status)->toBe('draft');
});

test('fetch transcript job sets status failed when source_url is empty for youtube', function () {
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'pending',
        'source_url' => '',
        'source_text' => null,
    ]);

    (new FetchTranscriptJob($content->id))->handle(app(\App\Contracts\YoutubeTranscriptFetcherInterface::class));

    $content->refresh();
    expect($content->status)->toBe('failed');
    expect($content->source_text)->toBeNull();
    expect($content->processing_failure_reason)->toBe('Processing failed: missing YouTube source URL.');
});

test('fetch transcript job sets status failed when type is not youtube', function () {
    $content = Content::query()->create([
        'type' => 'book',
        'title' => 'Test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'pending',
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'source_text' => null,
    ]);

    (new FetchTranscriptJob($content->id))->handle(app(\App\Contracts\YoutubeTranscriptFetcherInterface::class));

    $content->refresh();
    expect($content->status)->toBe('failed');
    expect($content->source_text)->toBeNull();
    expect($content->processing_failure_reason)->toBe('Processing failed: transcript fetch is only supported for YouTube content.');
});

test('fetch transcript job sets status failed when provider reports no captions', function () {
    config()->set('transcripts.youtube.driver', 'supadata');
    config()->set('transcripts.youtube.drivers.supadata.api_key', 'test-key');

    Http::preventStrayRequests();
    Http::fake([
        'https://api.supadata.ai/v1/transcript*' => Http::response([
            'message' => 'Transcript is unavailable for this video.',
        ], 206),
    ]);

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'No captions video',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'pending',
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'source_text' => null,
    ]);

    expect(fn () => (new FetchTranscriptJob($content->id))->handle(app(\App\Contracts\YoutubeTranscriptFetcherInterface::class)))
        ->toThrow(\App\Exceptions\TranscriptUnavailableException::class);

    $content->refresh();
    expect($content->status)->toBe('failed');
    expect($content->source_text)->toBeNull();
    expect($content->processing_failure_reason)->toBe('Transcript is unavailable for this video.');
});

test('fetch transcript job polls async transcript result before processing content', function () {
    config()->set('transcripts.youtube.driver', 'supadata');
    config()->set('transcripts.youtube.drivers.supadata.api_key', 'test-key');
    config()->set('transcripts.youtube.drivers.supadata.poll_interval_ms', 0);
    config()->set('transcripts.youtube.drivers.supadata.max_polls', 3);

    Http::preventStrayRequests();
    $jobStatusSequence = Http::sequence()
        ->push([
            'status' => 'queued',
        ], 200)
        ->push([
            'status' => 'completed',
            'content' => 'Async transcript result.',
            'lang' => 'en',
            'availableLangs' => ['en'],
        ], 200);

    Http::fake(function (\Illuminate\Http\Client\Request $request) use ($jobStatusSequence) {
        $url = $request->url();

        if ($url === 'https://api.supadata.ai/v1/transcript/job-123') {
            return $jobStatusSequence($request);
        }

        if (str_starts_with($url, 'https://api.supadata.ai/v1/transcript?')) {
            return Http::response([
                'jobId' => 'job-123',
            ], 202);
        }

        return null;
    });

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Async transcript video',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'pending',
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'source_text' => null,
    ]);

    (new FetchTranscriptJob($content->id))->handle(app(\App\Contracts\YoutubeTranscriptFetcherInterface::class));

    $content->refresh();
    expect($content->source_text)->toBe('Async transcript result.');
    expect($content->status)->toBe('ready');
    expect($content->processing_failure_reason)->toBeNull();
});

test('fetch transcript job does not overwrite moderation comment when technical failure happens', function () {
    config()->set('transcripts.youtube.driver', 'supadata');
    config()->set('transcripts.youtube.drivers.supadata.api_key', 'test-key');

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Moderated content',
        'language' => 'en',
        'origin' => 'user-submitted',
        'status' => 'pending',
        'source_url' => 'https://invalid-no-video-id',
        'source_text' => null,
        'moderation_comment' => 'Needs human review.',
    ]);

    expect(fn () => (new FetchTranscriptJob($content->id))->handle(app(\App\Contracts\YoutubeTranscriptFetcherInterface::class)))
        ->toThrow(\App\Exceptions\InvalidYoutubeUrlException::class);

    $content->refresh();
    expect($content->moderation_comment)->toBe('Needs human review.')
        ->and($content->processing_failure_reason)->toBe('Invalid YouTube URL: cannot extract video ID.');
});
