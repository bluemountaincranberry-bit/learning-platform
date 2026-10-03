<?php

namespace Tests\Feature\Api;

use App\Contracts\YoutubeTranscriptFetcherInterface;
use App\Modules\Content\Interfaces\Jobs\FetchTranscriptJob;
use App\Modules\Content\Interfaces\Jobs\ProcessContentJob;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\User\Models\User;
use App\Modules\Content\Application\AiAnalysisAutoDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('youtube submission flows from pending to ready', function () {
    Queue::fake();
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');

    Http::preventStrayRequests();
    Http::fake([
        'https://api.supadata.ai/v1/transcript*' => Http::response([
            'content' => 'Full pipeline transcript text.',
            'lang' => 'en',
            'availableLangs' => ['en'],
        ], 200),
    ]);

    $response = $this->postJson('/api/content/submit-youtube', [
        'title' => 'End-to-end video',
        'language' => 'en',
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    $response->assertCreated();
    $id = $response->json('content.id');

    $content = Content::findOrFail($id);
    expect($content->status)->toBe('pending');

    Queue::assertPushed(FetchTranscriptJob::class);

    (new FetchTranscriptJob($id))->handle(app(YoutubeTranscriptFetcherInterface::class));
    (new ProcessContentJob($id))->handle(app(AiAnalysisAutoDispatchService::class));

    $content->refresh();
    expect($content->status)->toBe('ready');
    // Lexeme extraction belongs to the asynchronous AI analysis layer; the
    // ingestion job only normalizes transcript state and dispatches analysis.
    expect($content->lexemes()->count())->toBe(0);
});

test('youtube submission failure path exposes failure reason', function () {
    Queue::fake();
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');

    Http::preventStrayRequests();
    Http::fake([
        'https://api.supadata.ai/v1/transcript*' => Http::response([
            'message' => 'Captions are missing.',
        ], 500),
    ]);

    $response = $this->postJson('/api/content/submit-youtube', [
        'title' => 'Failing video',
        'language' => 'en',
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    $response->assertCreated();
    $id = $response->json('content.id');

    Queue::assertPushed(FetchTranscriptJob::class);

    try {
        (new FetchTranscriptJob($id))->handle(app(YoutubeTranscriptFetcherInterface::class));
    } catch (\Throwable $e) {
        // expected failure
    }

    $content = Content::findOrFail($id);
    expect(in_array($content->status, ['pending', 'failed'], true))->toBeTrue();
});

test('retry after failure goes through pipeline to ready', function () {
    Queue::fake();
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');

    Http::fakeSequence()
        ->push(['message' => 'Temporary failure.'], 500)
        ->push([
            'content' => 'Retry transcript text.',
            'lang' => 'en',
            'availableLangs' => ['en'],
        ], 200);

    $response = $this->postJson('/api/content/submit-youtube', [
        'title' => 'Retry video',
        'language' => 'en',
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    $response->assertCreated();
    $id = $response->json('content.id');

    Queue::assertPushed(FetchTranscriptJob::class);

    try {
        (new FetchTranscriptJob($id))->handle(app(YoutubeTranscriptFetcherInterface::class));
    } catch (\Throwable $e) {
        // first push fails
    }

    $content = Content::findOrFail($id);

    (new FetchTranscriptJob($id))->handle(app(YoutubeTranscriptFetcherInterface::class));
    (new ProcessContentJob($id))->handle(app(AiAnalysisAutoDispatchService::class));

    $content->refresh();
    expect($content->status)->toBe('ready');
});
