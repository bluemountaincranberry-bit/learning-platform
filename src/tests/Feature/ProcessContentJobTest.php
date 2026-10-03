<?php

use App\Modules\Content\Interfaces\Jobs\ProcessContentJob;
use App\Modules\Ai\Interfaces\Jobs\RunAiContentAnalysisJob;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Application\AiAnalysisAutoDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('process content job does not create learner lexemes before AI analysis', function () {
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'pending',
        'source_text' => 'Hello world. Hello again!',
    ]);

    (new ProcessContentJob($content->id))->handle(app(AiAnalysisAutoDispatchService::class));

    $content->refresh();
    expect($content->status)->toBe('ready');
    expect($content->processing_completed_at)->not->toBeNull();
    expect($content->lexemes)->toHaveCount(0);
});

test('process content job sets status ready when source text is empty', function () {
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'pending',
        'source_text' => '   ',
    ]);

    (new ProcessContentJob($content->id))->handle(app(AiAnalysisAutoDispatchService::class));

    $content->refresh();
    expect($content->status)->toBe('ready');
    expect($content->processing_completed_at)->not->toBeNull();
    expect($content->lexemes)->toHaveCount(0);
});

test('process content job does nothing when content status is not pending or processing', function () {
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'draft',
        'source_text' => 'Hello world',
    ]);

    (new ProcessContentJob($content->id))->handle(app(AiAnalysisAutoDispatchService::class));

    $content->refresh();
    expect($content->status)->toBe('draft');
    expect($content->lexemes)->toHaveCount(0);
});

test('process content job marks content ready without creating tokenizer lexemes', function () {
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Test',
        'language' => 'en',
        'origin' => 'user-submitted',
        'status' => 'pending',
        'source_text' => 'Hello world',
    ]);

    (new ProcessContentJob($content->id))->handle(app(AiAnalysisAutoDispatchService::class));
    $content->refresh();
    expect($content->status)->toBe('ready');
    expect($content->lexemes)->toHaveCount(0);
});

test('process content job auto-dispatches AI analysis once processing succeeds', function () {
    config(['ai.enabled' => true]);
    Queue::fake();

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'pending',
        'source_text' => 'Hello world',
    ]);

    (new ProcessContentJob($content->id))->handle(app(AiAnalysisAutoDispatchService::class));

    Queue::assertPushed(RunAiContentAnalysisJob::class);
    expect($content->fresh()->latestAnalysisRun)->not->toBeNull();
});

test('process content job does not auto-dispatch AI analysis when transcript is empty', function () {
    config(['ai.enabled' => true]);
    Queue::fake();

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'pending',
        'source_text' => '   ',
    ]);

    Queue::assertNotPushed(RunAiContentAnalysisJob::class);
    (new ProcessContentJob($content->id))->handle(app(AiAnalysisAutoDispatchService::class));
    expect($content->fresh()->status)->toBe('ready');
});

test('process content job replaces existing lexemes', function () {
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'pending',
        'source_text' => 'New words',
    ]);
    $content->lexemes()->create([
        'type' => ContentLexeme::TYPE_WORD,
        'text' => 'old',
        'sort_order' => 1,
    ]);

    (new ProcessContentJob($content->id))->handle(app(AiAnalysisAutoDispatchService::class));

    $content->refresh();
    expect($content->lexemes)->toHaveCount(0);
});
