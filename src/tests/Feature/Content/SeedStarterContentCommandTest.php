<?php

use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\EmbeddingsClientInterface;
use App\Contracts\YoutubeTranscriptFetcherInterface;
use App\Modules\Content\Application\Transcript\TranscriptDocument;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Interfaces\Jobs\FetchTranscriptJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('starter command fills an empty catalog with words and grammar and is repeatable', function () {
    Http::preventStrayRequests();
    config(['ai.enabled' => true]);
    $fetcher = Mockery::mock(YoutubeTranscriptFetcherInterface::class);
    $fetcher->shouldReceive('fetch')->times(6)->with(Mockery::type('string'), 'en')
        ->andReturn(TranscriptDocument::fromPlainText('I have been waiting for inspiration.', 'en', 'test'));
    app()->instance(YoutubeTranscriptFetcherInterface::class, $fetcher);
    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->times(6)->andReturn([
        'lexemes' => [['text' => 'inspiration', 'type' => 'word', 'translation' => 'вдохновение', 'level' => 'B2', 'confidence' => 0.95]],
        'grammar' => [['title' => 'Present Perfect Continuous', 'summary' => 'An ongoing action.', 'example' => 'I have been waiting', 'confidence' => 0.95]],
    ]);
    app()->instance(AiJsonClient::class, $client);
    $embeddings = Mockery::mock(EmbeddingsClientInterface::class);
    $embeddings->shouldReceive('embed')->andReturn([1.0, 0.0]);
    app()->instance(EmbeddingsClientInterface::class, $embeddings);

    $this->artisan('content:seed-starter')->expectsOutputToContain('Created: 6; skipped: 0')->assertSuccessful();
    $this->getJson('/api/content?language=en')->assertOk()->assertJsonCount(6, 'data');
    foreach (Content::all() as $content) {
        expect($content->status)->toBe('ready')
            ->and($content->origin)->toBe('curated')
            ->and($content->lexemes()->count())->toBeGreaterThan(0)
            ->and($content->grammarRules()->count())->toBeGreaterThan(0);
    }

    $this->artisan('content:seed-starter')->expectsOutputToContain('Created: 0; skipped: 6')->assertSuccessful();
    $this->getJson('/api/content?language=en')->assertJsonCount(6, 'data');
});

test('starter command preserves an existing studied video and deduplicates URL variants in the list', function () {
    Queue::fake();
    config(['ai.enabled' => true, 'starter-content.talks' => [
        ['url' => 'https://www.youtube.com/watch?v=FWTNMzK9vG4', 'title' => 'New title', 'language' => 'en', 'level' => 'B2'],
        ['url' => 'https://youtu.be/JnfBXjWm7hc?si=share', 'title' => 'Matt Cutts', 'language' => 'en', 'level' => 'B1'],
        ['url' => 'https://www.youtube.com/embed/JnfBXjWm7hc', 'title' => 'Same video', 'language' => 'en', 'level' => 'B1'],
    ]]);
    $existing = Content::factory()->create([
        'type' => 'youtube', 'source_url' => 'https://youtu.be/FWTNMzK9vG4?si=original',
        'title' => 'Studied source', 'status' => 'failed', 'source_text' => 'My existing transcript.',
    ]);
    $attributes = $existing->refresh()->getAttributes();

    $this->artisan('content:seed-starter')->expectsOutputToContain('Created: 1; skipped: 2')->assertSuccessful();
    expect($existing->refresh()->getAttributes())->toBe($attributes)
        ->and(Content::count())->toBe(2);
    Queue::assertPushed(FetchTranscriptJob::class, 1);
    $this->artisan('content:seed-starter')->expectsOutputToContain('Created: 0; skipped: 3')->assertSuccessful();
    Queue::assertPushed(FetchTranscriptJob::class, 1);
});

test('starter preview works without AI and never queues or writes content', function () {
    Queue::fake();
    $this->artisan('content:seed-starter', ['--dry-run' => true])
        ->expectsOutputToContain('Would create: 6; skipped: 0')->assertSuccessful();
    expect(Content::count())->toBe(0);
    Queue::assertNothingPushed();
    $this->artisan('content:seed-starter')->assertFailed();
    expect(Content::count())->toBe(0);
});

test('starter command validates the whole list before importing', function () {
    Queue::fake();
    $talks = config('starter-content.talks');
    $talks[] = ['url' => 'https://evil.example/watch?v=JnfBXjWm7hc', 'title' => 'Invalid', 'language' => 'en', 'level' => 'B1'];
    config(['ai.enabled' => true, 'starter-content.talks' => $talks]);
    $this->artisan('content:seed-starter')->assertFailed();
    expect(Content::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('starter command refuses overlapping imports', function () {
    $lock = Cache::lock('content:seed-starter', 3600);
    $lock->get();
    try {
        $this->artisan('content:seed-starter')->assertFailed();
        expect(Content::count())->toBe(0);
    } finally {
        $lock->release();
    }
});

test('a transcript failure is visible and a rerun does not duplicate the failed video', function () {
    config(['ai.enabled' => true, 'starter-content.talks' => [config('starter-content.talks.0')]]);
    $fetcher = Mockery::mock(YoutubeTranscriptFetcherInterface::class);
    $fetcher->shouldReceive('fetch')->once()->andThrow(new RuntimeException('Captions unavailable.'));
    app()->instance(YoutubeTranscriptFetcherInterface::class, $fetcher);

    $this->artisan('content:seed-starter')->expectsOutputToContain('Processing request failed for #')->assertFailed();
    expect(Content::sole()->status)->toBe('failed')
        ->and(Content::sole()->processing_failure_reason)->toBe('Captions unavailable.');
    $this->artisan('content:seed-starter')->expectsOutputToContain('Created: 0; skipped: 1')->assertSuccessful();
    expect(Content::count())->toBe(1);
});
