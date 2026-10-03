<?php

use App\Modules\Ai\Domain\Models\AgentGraphRun;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Ai\Domain\Models\PersistedGraphDefinition;
use App\Modules\Ai\Application\Agent\Graph\GraphDefinitionTestRunService;
use App\Modules\Ai\Application\AiCandidateApplyService;
use App\Contracts\Ai\AiJsonClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function persistedAiAnalysisVersion(array $nodes, array $edges = []): array
{
    $record = PersistedGraphDefinition::query()->create(['key' => 'ai_analysis', 'name' => 'AI analysis']);
    $version = $record->versions()->create(['version' => 1, 'nodes' => $nodes, 'edges' => $edges]);

    return [$record, $version];
}

test('testRun() runs analyze then match then pauses at the human checkpoint, same as a real run', function () {
    [, $version] = persistedAiAnalysisVersion([
        ['key' => 'analyze', 'node' => 'analyze'],
        ['key' => 'match', 'node' => 'match'],
        ['key' => 'review_checkpoint', 'node' => 'human_checkpoint'],
        ['key' => 'apply', 'node' => 'apply'],
    ], [
        ['from' => 'analyze', 'to' => 'match'],
        ['from' => 'match', 'to' => 'review_checkpoint'],
        ['from' => 'review_checkpoint', 'to' => 'apply'],
    ]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [['text' => 'get up', 'type' => 'phrasal_verb', 'translation' => 'вставать']],
        'grammar' => [],
    ]);
    app()->instance(AiJsonClient::class, $client);

    $result = app(GraphDefinitionTestRunService::class)->testRun('ai_analysis', $version->id, 'I need to get up early.');

    expect($result->status)->toBe('paused')
        ->and($result->currentNode)->toBe('review_checkpoint')
        ->and($result->pauseReason)->not->toBeNull()
        ->and($result->lexemeCandidates)->toHaveCount(1)
        ->and($result->lexemeCandidates[0]['text'])->toBe('get up');
});

test('testRun() leaves no trace in the database — Content/AiAnalysisRun/candidates are all rolled back', function () {
    [, $version] = persistedAiAnalysisVersion([
        ['key' => 'analyze', 'node' => 'analyze'],
    ]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [['text' => 'run', 'type' => 'word']],
        'grammar' => [],
    ]);
    app()->instance(AiJsonClient::class, $client);

    app(GraphDefinitionTestRunService::class)->testRun('ai_analysis', $version->id, 'I run every day.');

    expect(Content::query()->count())->toBe(0)
        ->and(AgentGraphRun::query()->count())->toBe(0);
});

test('testRun() never calls AiCandidateApplyService::apply(), even for a definition that reaches apply directly (no checkpoint in front)', function () {
    [, $version] = persistedAiAnalysisVersion([
        ['key' => 'apply', 'node' => 'apply'],
    ]);

    $applyService = Mockery::mock(AiCandidateApplyService::class);
    $applyService->shouldNotReceive('apply');
    app()->instance(AiCandidateApplyService::class, $applyService);

    $result = app(GraphDefinitionTestRunService::class)->testRun('ai_analysis', $version->id, 'irrelevant, apply does not read the transcript');

    expect($result->status)->toBe('completed');
});

test('testRun() throws when the version id does not belong to the given graph key', function () {
    $record = PersistedGraphDefinition::query()->create(['key' => 'other_graph', 'name' => 'Other']);
    $version = $record->versions()->create(['version' => 1, 'nodes' => [['key' => 'a', 'node' => 'analyze']], 'edges' => []]);

    expect(fn () => app(GraphDefinitionTestRunService::class)->testRun('ai_analysis', $version->id, 'x'))
        ->toThrow(InvalidArgumentException::class);
});

test('testRun() throws for a version whose nodes reference an unregistered node_key', function () {
    [, $version] = persistedAiAnalysisVersion([
        ['key' => 'a', 'node' => 'not_a_real_node_type'],
    ]);

    expect(fn () => app(GraphDefinitionTestRunService::class)->testRun('ai_analysis', $version->id, 'x'))
        ->toThrow(InvalidArgumentException::class);
});
