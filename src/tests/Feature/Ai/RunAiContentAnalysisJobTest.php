<?php

use App\Exceptions\AiClientException;
use App\Modules\Ai\Interfaces\Jobs\RunAiContentAnalysisJob;
use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Ai\Application\CandidateMatchingService;
use App\Contracts\Ai\AiJsonClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('job marks run running then completed and stores candidates on success', function () {
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
        'source_text' => 'I have been waiting for you to get up.',
    ]);
    $run = $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_PENDING]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [['text' => 'get up', 'type' => 'phrasal_verb', 'translation' => 'вставать']],
        'grammar' => [],
    ]);
    $this->app->instance(AiJsonClient::class, $client);

    app()->call([new RunAiContentAnalysisJob($run->id), 'handle']);

    $run->refresh();
    expect($run->status)->toBe(AiAnalysisRun::STATUS_COMPLETED)
        ->and($run->started_at)->not->toBeNull()
        ->and($run->completed_at)->not->toBeNull()
        ->and($run->lexemeCandidates()->count())->toBe(1);
});

test('job marks run failed and rethrows when analysis throws', function () {
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
        'source_text' => 'Some transcript text.',
    ]);
    $run = $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_PENDING]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andThrow(new AiClientException('Rate limited'));
    $this->app->instance(AiJsonClient::class, $client);

    expect(fn () => app()->call([new RunAiContentAnalysisJob($run->id), 'handle']))
        ->toThrow(AiClientException::class, 'Rate limited');

    $run->refresh();
    expect($run->status)->toBe(AiAnalysisRun::STATUS_FAILED)
        ->and($run->failure_reason)->toBe('Rate limited');
});

test('job does nothing when run is not pending', function () {
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
        'source_text' => 'text',
    ]);
    $run = $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_COMPLETED]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldNotReceive('completeJson');
    $this->app->instance(AiJsonClient::class, $client);

    app()->call([new RunAiContentAnalysisJob($run->id), 'handle']);

    $run->refresh();
    expect($run->status)->toBe(AiAnalysisRun::STATUS_COMPLETED);
});

test('job runs candidate matching after a successful analysis', function () {
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
        'source_text' => 'text',
    ]);
    $run = $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_PENDING]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [['text' => 'run']],
        'grammar' => [],
    ]);
    $this->app->instance(AiJsonClient::class, $client);

    $matcher = Mockery::mock(CandidateMatchingService::class);
    $matcher->shouldReceive('matchRun')->once()->with(Mockery::on(fn ($arg) => $arg->is($run)));
    $this->app->instance(CandidateMatchingService::class, $matcher);

    app()->call([new RunAiContentAnalysisJob($run->id), 'handle']);

    $run->refresh();
    expect($run->status)->toBe(AiAnalysisRun::STATUS_COMPLETED);
});

test('job still completes when candidate matching throws', function () {
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
        'source_text' => 'text',
    ]);
    $run = $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_PENDING]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [['text' => 'run']],
        'grammar' => [],
    ]);
    $this->app->instance(AiJsonClient::class, $client);

    $matcher = Mockery::mock(CandidateMatchingService::class);
    $matcher->shouldReceive('matchRun')->once()->andThrow(new \RuntimeException('embeddings API down'));
    $this->app->instance(CandidateMatchingService::class, $matcher);

    app()->call([new RunAiContentAnalysisJob($run->id), 'handle']);

    $run->refresh();
    expect($run->status)->toBe(AiAnalysisRun::STATUS_COMPLETED)
        ->and($run->lexemeCandidates()->count())->toBe(1);
});
