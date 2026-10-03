<?php

use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\Agent\Tools\Grammar\ErrorAnalysisTool;
use App\Modules\Ai\Application\Agent\Tools\Grammar\GrammarExplanationTool;
use App\Modules\Ai\Application\Agent\Tools\Grammar\GrammarSearchTool;
use App\Modules\Ai\Application\RagIndexingService;
use App\Contracts\Ai\AiJsonClient;
use App\Modules\Ai\Infrastructure\ElasticsearchClient;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// Same RAG test harness as LearningToolsTest (task 2.5) — real Elasticsearch
// already running in this environment, only the embeddings HTTP call faked.
beforeEach(function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['data' => [['embedding' => array_fill(0, 1536, 0.01)]]], 200),
    ]);
    config(['ai.openai.api_key' => 'test-key']);

    $this->ragIndex = 'rag_corpus_test_'.str_replace('.', '', uniqid('', true));
    config(['elasticsearch.enabled' => true, 'elasticsearch.rag.index' => $this->ragIndex]);
});

afterEach(function () {
    app(ElasticsearchClient::class)->deleteIndex($this->ragIndex);
});

// --- GrammarSearchTool ------------------------------------------------

test('sideEffect is read_only for all three GrammarAgent tools', function () {
    expect(app(GrammarSearchTool::class)->definition()->sideEffect)->toBe(AgentToolDefinition::SIDE_EFFECT_READ_ONLY)
        ->and(app(GrammarExplanationTool::class)->definition()->sideEffect)->toBe(AgentToolDefinition::SIDE_EFFECT_READ_ONLY)
        ->and(app(ErrorAnalysisTool::class)->definition()->sideEffect)->toBe(AgentToolDefinition::SIDE_EFFECT_READ_ONLY);
});

test('grammar search returns multiple candidates, not a single hydrated rule', function () {
    $topic = GrammarTopic::query()->create(['slug' => 'pp-topic-'.uniqid(), 'language' => 'en', 'name' => 'Present Perfect', 'status' => 'active']);
    $rule = GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => 'pp-rule-'.uniqid(), 'language' => 'en',
        'title' => 'Present Perfect', 'status' => 'published', 'summary' => 'Past action, present relevance.',
        'body' => 'have/has + past participle',
    ]);
    app(RagIndexingService::class)->indexGrammarRules([$rule->id]);

    $result = app(GrammarSearchTool::class)->execute(['query' => 'present perfect'], new AgentToolContext(1, 1));

    expect($result['candidates'])->toHaveCount(1)
        ->and($result['candidates'][0]['grammar_rule_id'])->toBe($rule->id)
        ->and($result['candidates'][0])->toHaveKey('score')
        ->and($result['candidates'][0])->not->toHaveKey('body');
});

test('grammar search requires a query', function () {
    $result = app(GrammarSearchTool::class)->execute([], new AgentToolContext(1, 1));

    expect($result)->toHaveKey('error');
});

// --- GrammarExplanationTool --------------------------------------------

test('grammar explanation lookup hydrates the full published rule with tool_output-wrapped body', function () {
    $topic = GrammarTopic::query()->create(['slug' => 'pp2-'.uniqid(), 'language' => 'en', 'name' => 'PP', 'status' => 'active']);
    $rule = GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => 'pp2-rule-'.uniqid(), 'language' => 'en',
        'title' => 'Present Perfect', 'status' => 'published', 'summary' => 'summary text', 'body' => 'have/has + past participle',
    ]);
    $rule->examples()->create(['language' => 'en', 'example' => 'I have finished.', 'is_primary' => true, 'sort_order' => 1]);

    $result = app(GrammarExplanationTool::class)->execute(['grammar_rule_id' => $rule->id], new AgentToolContext(1, 1));

    expect($result['found'])->toBeTrue()
        ->and($result['title'])->toBe('Present Perfect')
        ->and($result['body'])->toContain('<tool_output>')
        ->and($result['body'])->toContain('have/has + past participle')
        ->and($result['examples'])->toHaveCount(1);
});

test('grammar explanation lookup does not return draft rules', function () {
    $topic = GrammarTopic::query()->create(['slug' => 'draft-'.uniqid(), 'language' => 'en', 'name' => 'Draft', 'status' => 'active']);
    $rule = GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => 'draft-rule-'.uniqid(), 'language' => 'en',
        'title' => 'Draft rule', 'status' => 'draft',
    ]);

    $result = app(GrammarExplanationTool::class)->execute(['grammar_rule_id' => $rule->id], new AgentToolContext(1, 1));

    expect($result['found'])->toBeFalse();
});

test('grammar explanation lookup requires a valid integer id', function () {
    $result = app(GrammarExplanationTool::class)->execute(['grammar_rule_id' => 'not-a-number'], new AgentToolContext(1, 1));

    expect($result)->toHaveKey('error');
});

// --- ErrorAnalysisTool --------------------------------------------------

test('error analysis reports whether the candidate rule applies and the correction', function () {
    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'rule_applies' => true,
        'has_error' => true,
        'error_description' => 'Used simple past instead of present perfect.',
        'corrected_sentence' => 'I have finished my homework.',
    ]);
    app()->instance(AiJsonClient::class, $client);

    $result = app(ErrorAnalysisTool::class)->execute([
        'student_sentence' => 'I finished my homework already.',
        'candidate_rule_title' => 'Present Perfect',
        'candidate_rule_summary' => 'Past action, present relevance.',
    ], new AgentToolContext(1, 1));

    expect($result['rule_applies'])->toBeTrue()
        ->and($result['has_error'])->toBeTrue()
        ->and($result['corrected_sentence'])->toBe('I have finished my homework.');
});

test('error analysis requires student_sentence and candidate_rule_title', function () {
    $result = app(ErrorAnalysisTool::class)->execute(['student_sentence' => 'x'], new AgentToolContext(1, 1));

    expect($result)->toHaveKey('error');
});
