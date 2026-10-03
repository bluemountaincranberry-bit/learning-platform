<?php

use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Tools\Student\ExplainGrammarTool;
use App\Modules\Ai\Application\RagRetrievalService;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('explain grammar hydrates a published rule from Content after retrieval', function () {
    $topic = GrammarTopic::query()->create(['slug' => 'lookup-topic', 'language' => 'en', 'name' => 'Present Perfect', 'status' => 'active']);
    $rule = GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => 'lookup-rule', 'language' => 'en',
        'title' => 'Present Perfect', 'status' => 'published', 'summary' => 'Present relevance',
        'body' => 'have + participle',
    ]);
    $rule->examples()->create(['language' => 'en', 'example' => 'I have finished.', 'translation' => 'Я закончил.', 'sort_order' => 1]);

    $rag = Mockery::mock(RagRetrievalService::class);
    $rag->shouldReceive('retrieve')->once()->andReturn([['source_id' => $rule->id, 'score' => 0.9, 'text' => 'have + participle']]);
    $rag->shouldReceive('wrapAsToolOutput')->once()->andReturn('<tool_output>have + participle</tool_output>');
    app()->instance(RagRetrievalService::class, $rag);

    $result = app(ExplainGrammarTool::class)->execute(['topic' => 'present perfect'], new AgentToolContext(1, 1));

    expect($result)->toMatchArray([
        'found' => true,
        'title' => 'Present Perfect',
        'summary' => 'Present relevance',
        'match_score' => 0.9,
    ])->and($result['examples'])->toBe([['example' => 'I have finished.', 'translation' => 'Я закончил.']]);
});

test('explain grammar does not expose a rule unpublished after indexing', function () {
    $topic = GrammarTopic::query()->create(['slug' => 'stale-topic', 'language' => 'en', 'name' => 'Stale', 'status' => 'active']);
    $rule = GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => 'stale-rule', 'language' => 'en',
        'title' => 'Stale rule', 'status' => 'draft',
    ]);

    $rag = Mockery::mock(RagRetrievalService::class);
    $rag->shouldReceive('retrieve')->once()->andReturn([['source_id' => $rule->id, 'score' => 0.9]]);
    app()->instance(RagRetrievalService::class, $rag);

    $result = app(ExplainGrammarTool::class)->execute(['topic' => 'stale'], new AgentToolContext(1, 1));

    expect($result['found'])->toBeFalse();
});
