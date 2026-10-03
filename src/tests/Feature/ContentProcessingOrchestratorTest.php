<?php

use App\Modules\Content\Interfaces\Jobs\FetchTranscriptJob;
use App\Modules\Content\Interfaces\Jobs\ProcessContentJob;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Application\Contracts\ContentProcessingOrchestratorInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('orchestrator dispatches transcript fetch for youtube without source text', function () {
    Queue::fake();

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'YouTube draft',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'failed',
        'processing_failure_reason' => 'Transcript provider timeout.',
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'source_text' => null,
    ]);

    $result = app(ContentProcessingOrchestratorInterface::class)->request($content);

    expect($result->accepted)->toBeTrue();

    $content->refresh();
    expect($content->status)->toBe('pending');
    expect($content->processing_failure_reason)->toBeNull();
    expect($content->processing_requested_at)->not->toBeNull();
    expect($content->processing_completed_at)->toBeNull();
    Queue::assertPushed(FetchTranscriptJob::class, 1);
    Queue::assertNotPushed(ProcessContentJob::class);
});

test('orchestrator dispatches content processing when source text is present', function () {
    Queue::fake();

    $content = Content::query()->create([
        'type' => 'movie',
        'title' => 'Movie draft',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'draft',
        'source_text' => 'One two three',
    ]);

    $result = app(ContentProcessingOrchestratorInterface::class)->request($content);

    expect($result->accepted)->toBeTrue();

    $content->refresh();
    expect($content->status)->toBe('pending');
    expect($content->processing_requested_at)->not->toBeNull();
    expect($content->processing_completed_at)->toBeNull();
    Queue::assertPushed(ProcessContentJob::class, 1);
    Queue::assertNotPushed(FetchTranscriptJob::class);
});

test('orchestrator blocks non-youtube processing without source text', function () {
    Queue::fake();

    $content = Content::query()->create([
        'type' => 'book',
        'title' => 'Book draft',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'draft',
        'source_text' => null,
    ]);

    $result = app(ContentProcessingOrchestratorInterface::class)->request($content);

    expect($result->accepted)->toBeFalse()
        ->and($result->reason)->toBe('Song, book, movie and grammar content require source text before processing');

    $content->refresh();
    expect($content->status)->toBe('draft');
    Queue::assertNothingPushed();
});

test('orchestrator blocks movie processing without source text', function () {
    Queue::fake();

    $content = Content::query()->create([
        'type' => 'movie',
        'title' => 'Movie draft',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'draft',
        'source_text' => null,
    ]);

    $result = app(ContentProcessingOrchestratorInterface::class)->request($content);

    expect($result->accepted)->toBeFalse()
        ->and($result->reason)->toBe('Song, book, movie and grammar content require source text before processing');

    $content->refresh();
    expect($content->status)->toBe('draft');
    Queue::assertNothingPushed();
});
