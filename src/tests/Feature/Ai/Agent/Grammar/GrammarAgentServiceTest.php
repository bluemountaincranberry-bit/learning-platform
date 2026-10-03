<?php

use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Ai\Application\Agent\Data\AgentChatResponse;
use App\Modules\Ai\Application\Agent\Data\AgentToolCall;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\GrammarAgentService;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use App\Modules\Ai\Application\RagIndexingService;
use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\AiToolCallingClient;
use App\Modules\Ai\Infrastructure\ElasticsearchClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

/**
 * Scenario tests in the same style as ContentAgentServiceTest (task 1.1) —
 * a fake AiToolCallingClient scripting exactly what a real model would do
 * for this specific request, run through the real container-wired
 * GrammarAgentService (so AiServiceProvider's sideEffect-at-wiring-time
 * check also runs).
 */
uses(RefreshDatabase::class);

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

test('grammar agent searches, analyzes the student sentence, then explains the matching rule', function () {
    $topic = GrammarTopic::query()->create(['slug' => 'pp-'.uniqid(), 'language' => 'en', 'name' => 'Present Perfect', 'status' => 'active']);
    $rule = GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => 'pp-rule-'.uniqid(), 'language' => 'en',
        'title' => 'Present Perfect', 'status' => 'published', 'summary' => 'Past action, present relevance.',
        'body' => 'have/has + past participle',
    ]);
    app(RagIndexingService::class)->indexGrammarRules([$rule->id]);

    $jsonClient = Mockery::mock(AiJsonClient::class);
    $jsonClient->shouldReceive('completeJson')->once()->andReturn([
        'rule_applies' => true,
        'has_error' => true,
        'error_description' => 'Used simple past instead of present perfect.',
        'corrected_sentence' => 'I have finished my homework.',
    ]);
    app()->instance(AiJsonClient::class, $jsonClient);

    $callCount = 0;
    $toolClient = Mockery::mock(AiToolCallingClient::class);
    $toolClient->shouldReceive('chat')->times(4)->andReturnUsing(function () use (&$callCount, $rule) {
        $callCount++;

        return match ($callCount) {
            1 => new AgentChatResponse(null, [
                new AgentToolCall('call_1', 'grammar_search', ['query' => 'I finished my homework already.']),
            ]),
            2 => new AgentChatResponse(null, [
                new AgentToolCall('call_2', 'analyze_grammar_error', [
                    'student_sentence' => 'I finished my homework already.',
                    'candidate_rule_title' => 'Present Perfect',
                    'candidate_rule_summary' => 'Past action, present relevance.',
                ]),
            ]),
            3 => new AgentChatResponse(null, [
                new AgentToolCall('call_3', 'grammar_explanation_lookup', ['grammar_rule_id' => $rule->id]),
            ]),
            default => new AgentChatResponse('You should use the present perfect here: "I have finished my homework."'),
        };
    });
    app()->instance(AiToolCallingClient::class, $toolClient);

    $result = app(GrammarAgentService::class)->run(
        'The student wrote: "I finished my homework already." What is wrong?',
        new AgentToolContext(1, 1),
        TraceContext::newTrace()
    );

    expect($result)->toBe('You should use the present perfect here: "I have finished my homework."');
});

test('grammar agent stops gracefully at its iteration limit', function () {
    $toolClient = Mockery::mock(AiToolCallingClient::class);
    $toolClient->shouldReceive('chat')->times(5)->andReturn(
        new AgentChatResponse(null, [new AgentToolCall('call_x', 'grammar_search', ['query' => 'loop forever'])])
    );
    app()->instance(AiToolCallingClient::class, $toolClient);

    $result = app(GrammarAgentService::class)->run('confusing request', new AgentToolContext(1, 1), TraceContext::newTrace());

    expect($result)->toContain("couldn't finish");
});

test('grammar agent blueprint only allows read_only tools, matching ADR-002 (no draft/publish action needed)', function () {
    expect(GrammarAgentService::blueprint()->allowedSideEffects)->toBe([
        \App\Modules\Ai\Application\Agent\Data\AgentToolDefinition::SIDE_EFFECT_READ_ONLY,
    ]);
});
