<?php

use App\Modules\Ai\Domain\Models\AgentGraphRun;
use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Ai\Domain\Models\PersistedGraphDefinition;
use App\Modules\Ai\Application\Agent\Graph\AiAnalysisGraphService;
use App\Modules\Ai\Application\Agent\Graph\Definitions\AiAnalysisGraph;
use App\Modules\Ai\Application\Agent\Graph\GraphRunResult;
use App\Modules\Ai\Application\CandidateMatchingService;
use App\Contracts\Ai\AiJsonClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function fixtureAnalysisRun(): AiAnalysisRun
{
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Graph fixture',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
        'source_text' => 'I have been waiting for you to get up all morning.',
    ]);

    return $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_PENDING]);
}

test('the graph runs analyze then match then pauses for human review, mirroring the existing pipeline', function () {
    $run = fixtureAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [['text' => 'get up', 'type' => 'phrasal_verb', 'translation' => 'вставать']],
        'grammar' => [],
    ]);
    $this->app->instance(AiJsonClient::class, $client);

    $result = app(AiAnalysisGraphService::class)->start($run);

    expect($result->status)->toBe(GraphRunResult::STATUS_PAUSED)
        ->and($result->currentStepKey)->toBe('review_checkpoint')
        ->and($result->state->get('analyzed'))->toBeTrue()
        ->and($result->state->get('matched'))->toBeTrue();

    $run->refresh();
    expect($run->lexemeCandidates()->count())->toBe(1);

    // Task 8.2: pausing at review_checkpoint is "ready for review", the same
    // meaning `completed` has in the live RunAiContentAnalysisJob path today
    // (set right after best-effort matching, well before any human has
    // reviewed/applied anything).
    expect($run->status)->toBe(AiAnalysisRun::STATUS_COMPLETED)
        ->and($run->started_at)->not->toBeNull()
        ->and($run->completed_at)->not->toBeNull();

    $dbRun = AgentGraphRun::query()->where('graph_name', 'ai_analysis')->firstOrFail();
    expect($dbRun->status)->toBe(AgentGraphRun::STATUS_PAUSED)
        ->and($dbRun->current_node)->toBe('review_checkpoint')
        // No published PersistedGraphDefinition exists for ai_analysis in
        // this test — ran from the hand-built PHP class, so null here is
        // the correct value, not a bug.
        ->and($dbRun->graph_definition_version_id)->toBeNull();
});

test('start() actually runs a published PersistedGraphDefinition override, not the hand-built PHP class — the bug this test guards against made Publish silently do nothing', function () {
    $run = fixtureAnalysisRun();

    // Override wired to stop after `analyze` alone (no match/checkpoint/apply
    // at all) — if start() were still bypassing GraphDefinitionResolver (the
    // bug), this run would proceed all the way to review_checkpoint like the
    // unmodified hand-built graph, not stop after one step.
    $record = PersistedGraphDefinition::query()->create(['key' => AiAnalysisGraph::NAME, 'name' => 'AI analysis']);
    $version = $record->versions()->create([
        'version' => 1,
        'nodes' => [['key' => 'analyze', 'node' => 'analyze']],
        'edges' => [],
    ]);
    $record->update(['active_version_id' => $version->id]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [['text' => 'get up', 'type' => 'phrasal_verb', 'translation' => 'вставать']],
        'grammar' => [],
    ]);
    $this->app->instance(AiJsonClient::class, $client);

    $result = app(AiAnalysisGraphService::class)->start($run);

    expect($result->status)->toBe(GraphRunResult::STATUS_COMPLETED)
        ->and($result->currentStepKey)->toBe('analyze');

    $dbRun = AgentGraphRun::query()->where('graph_name', 'ai_analysis')->firstOrFail();
    expect($dbRun->graph_definition_version_id)->toBe($version->id);
});

test('a matching failure does not fail the graph, and apply still runs after approval (task 8.1)', function () {
    $run = fixtureAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [['text' => 'get up', 'type' => 'phrasal_verb', 'translation' => 'вставать']],
        'grammar' => [],
    ]);
    $this->app->instance(AiJsonClient::class, $client);

    $matcher = Mockery::mock(CandidateMatchingService::class);
    $matcher->shouldReceive('matchRun')->once()->andThrow(new \RuntimeException('embeddings API down'));
    $this->app->instance(CandidateMatchingService::class, $matcher);

    $service = app(AiAnalysisGraphService::class);
    $result = $service->start($run);

    expect($result->status)->toBe(GraphRunResult::STATUS_PAUSED)
        ->and($result->currentStepKey)->toBe('review_checkpoint')
        ->and($result->state->get('matched'))->toBeFalse();

    $run->refresh();
    expect($run->status)->toBe(AiAnalysisRun::STATUS_COMPLETED)
        ->and($run->lexemeCandidates()->count())->toBe(1);

    $dbRun = $service->latestRunFor($run);
    $run->lexemeCandidates()->update(['status' => \App\Modules\Content\Domain\Models\ContentLexemeCandidate::STATUS_ACCEPTED]);

    $applyResult = $service->approveAndResume($dbRun->id);

    expect($applyResult->status)->toBe(GraphRunResult::STATUS_COMPLETED)
        ->and($applyResult->state->get('applied'))->toBe(['lexemes' => 1, 'grammar' => 0]);
});

test('approveAndResume() applies accepted candidates and completes the run', function () {
    $run = fixtureAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [['text' => 'get up', 'type' => 'phrasal_verb', 'translation' => 'вставать']],
        'grammar' => [],
    ]);
    $this->app->instance(AiJsonClient::class, $client);

    $service = app(AiAnalysisGraphService::class);
    $paused = $service->start($run);
    $dbRun = $service->latestRunFor($run);

    $run->lexemeCandidates()->update(['status' => \App\Modules\Content\Domain\Models\ContentLexemeCandidate::STATUS_ACCEPTED]);

    $result = $service->approveAndResume($dbRun->id);

    expect($result->status)->toBe(GraphRunResult::STATUS_COMPLETED)
        ->and($result->state->get('applied'))->toBe(['lexemes' => 1, 'grammar' => 0]);

    $run->refresh();
    expect($run->lexemeCandidates()->first()->status)->toBe(\App\Modules\Content\Domain\Models\ContentLexemeCandidate::STATUS_APPLIED);

    // Task 8.2: approveAndResume()/ApplyNode must NOT touch AiAnalysisRun.status —
    // it was already set to `completed` by start() when the run first paused
    // at review_checkpoint, matching AiCandidateApplyService::apply() never
    // touching it on the live path either.
    expect($run->status)->toBe(AiAnalysisRun::STATUS_COMPLETED);

    $dbRun->refresh();
    expect($dbRun->status)->toBe(AgentGraphRun::STATUS_COMPLETED);
});

test('a failing analyze step marks the graph run failed without matching or applying', function () {
    $run = fixtureAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andThrow(new \App\Exceptions\AiClientException('Rate limited'));
    $this->app->instance(AiJsonClient::class, $client);

    $result = app(AiAnalysisGraphService::class)->start($run);

    expect($result->status)->toBe(GraphRunResult::STATUS_FAILED)
        ->and($result->currentStepKey)->toBe('analyze')
        ->and($result->failureReason)->toBe('Rate limited');

    // Task 8.2: mirrors RunAiContentAnalysisJobTest's "job marks run failed
    // and rethrows when analysis throws" for the live path.
    $run->refresh();
    expect($run->status)->toBe(AiAnalysisRun::STATUS_FAILED)
        ->and($run->failure_reason)->toBe('Rate limited')
        ->and($run->started_at)->not->toBeNull()
        ->and($run->completed_at)->not->toBeNull();

    $dbRun = AgentGraphRun::query()->where('graph_name', 'ai_analysis')->firstOrFail();
    expect($dbRun->status)->toBe(AgentGraphRun::STATUS_FAILED);
});
