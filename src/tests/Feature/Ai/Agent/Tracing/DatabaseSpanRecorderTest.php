<?php

use App\Modules\Ai\Domain\Models\AgentTrace;
use App\Modules\Ai\Domain\Models\AgentTraceSpan;
use App\Modules\Ai\Domain\Models\ModelPricing;
use App\Modules\Ai\Application\Agent\AgentLoop;
use App\Modules\Ai\Application\Agent\Contracts\AgentLoopObserver;
use App\Modules\Ai\Application\Agent\Data\AgentChatResponse;
use App\Modules\Ai\Application\Agent\Data\AgentToolCall;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Tracing\DatabaseSpanRecorder;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use App\Contracts\Ai\AiToolCallingClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

class NoopLoopObserver implements AgentLoopObserver
{
    public function onToolCallStarted(AgentToolCall $call): void {}

    public function onToolCallCompleted(AgentToolCall $call, array $result, int $latencyMs): void {}

    public function onFinalResponse(AgentChatResponse $response): void {}

    public function onIterationLimitReached(): void {}
}

test('startSpan writes a row, endSpan fills in ended_at/duration/status/tokens', function () {
    $recorder = new DatabaseSpanRecorder;
    $trace = TraceContext::newTrace();

    $spanId = $recorder->startSpan($trace, 'llm_call', 'agent_loop.chat', ['iteration' => 0]);

    $row = AgentTraceSpan::query()->where('span_id', $spanId)->firstOrFail();
    expect($row->trace_id)->toBe($trace->traceId)
        ->and($row->parent_span_id)->toBeNull()
        ->and($row->span_type)->toBe('llm_call')
        ->and($row->ended_at)->toBeNull()
        ->and($row->metadata)->toBe(['iteration' => 0]);

    $recorder->endSpan($spanId, 'ok', ['prompt_tokens' => 12, 'completion_tokens' => 4]);

    $row->refresh();
    expect($row->ended_at)->not->toBeNull()
        ->and($row->duration_ms)->toBeInt()
        ->and($row->status)->toBe('ok')
        ->and($row->prompt_tokens)->toBe(12)
        ->and($row->completion_tokens)->toBe(4)
        ->and($row->metadata)->toBe(['iteration' => 0]);
});

test('endSpan on a missing span_id does not throw', function () {
    (new DatabaseSpanRecorder)->endSpan('does-not-exist', 'ok');
})->throwsNoExceptions();

test('endSpan computes cost_usd for an llm_call span from config(ai.pricing) at a fixed price', function () {
    config(['ai.pricing.prompt_per_1k_usd' => 0.001, 'ai.pricing.completion_per_1k_usd' => 0.002]);

    $recorder = new DatabaseSpanRecorder;
    $trace = TraceContext::newTrace();
    $spanId = $recorder->startSpan($trace, 'llm_call', 'agent_loop.chat');

    $recorder->endSpan($spanId, 'ok', ['prompt_tokens' => 1000, 'completion_tokens' => 500]);

    $row = AgentTraceSpan::query()->where('span_id', $spanId)->firstOrFail();
    // 1000/1000 * 0.001 + 500/1000 * 0.002 = 0.001 + 0.001 = 0.002
    expect($row->cost_usd)->toBe(0.002);
});

test('endSpan uses model_pricing over config(ai.pricing) when a row exists for the span\'s model', function () {
    config(['ai.pricing.prompt_per_1k_usd' => 999, 'ai.pricing.completion_per_1k_usd' => 999]);
    ModelPricing::query()->create(['model' => 'gpt-4o', 'prompt_per_1k_usd' => 0.005, 'completion_per_1k_usd' => 0.015]);

    $recorder = new DatabaseSpanRecorder;
    $trace = TraceContext::newTrace();
    $spanId = $recorder->startSpan($trace, 'llm_call', 'agent_loop.chat');

    $recorder->endSpan($spanId, 'ok', ['prompt_tokens' => 1000, 'completion_tokens' => 1000, 'model' => 'gpt-4o']);

    $row = AgentTraceSpan::query()->where('span_id', $spanId)->firstOrFail();
    // 1000/1000 * 0.005 + 1000/1000 * 0.015 = 0.02 — proves model_pricing
    // won, not the wildly different config(ai.pricing) values set above.
    expect($row->model)->toBe('gpt-4o')
        ->and($row->cost_usd)->toBe(0.02);
});

test('endSpan falls back to config(ai.pricing) when no model_pricing row matches the span\'s model', function () {
    config(['ai.pricing.prompt_per_1k_usd' => 0.001, 'ai.pricing.completion_per_1k_usd' => 0.002]);

    $recorder = new DatabaseSpanRecorder;
    $trace = TraceContext::newTrace();
    $spanId = $recorder->startSpan($trace, 'llm_call', 'agent_loop.chat');

    $recorder->endSpan($spanId, 'ok', ['prompt_tokens' => 1000, 'completion_tokens' => 500, 'model' => 'gpt-4o-mini']);

    $row = AgentTraceSpan::query()->where('span_id', $spanId)->firstOrFail();
    expect($row->model)->toBe('gpt-4o-mini')
        ->and($row->cost_usd)->toBe(0.002);
});

test('endSpan falls back to config(ai.pricing) when the span has no model at all', function () {
    config(['ai.pricing.prompt_per_1k_usd' => 0.001, 'ai.pricing.completion_per_1k_usd' => 0.002]);

    $recorder = new DatabaseSpanRecorder;
    $trace = TraceContext::newTrace();
    $spanId = $recorder->startSpan($trace, 'llm_call', 'agent_loop.chat');

    $recorder->endSpan($spanId, 'ok', ['prompt_tokens' => 1000, 'completion_tokens' => 500]);

    $row = AgentTraceSpan::query()->where('span_id', $spanId)->firstOrFail();
    expect($row->model)->toBeNull()
        ->and($row->cost_usd)->toBe(0.002);
});

test('endSpan leaves cost_usd null for a non-llm_call span', function () {
    $recorder = new DatabaseSpanRecorder;
    $trace = TraceContext::newTrace();
    $spanId = $recorder->startSpan($trace, 'tool_call', 'some_tool');

    $recorder->endSpan($spanId, 'ok');

    $row = AgentTraceSpan::query()->where('span_id', $spanId)->firstOrFail();
    expect($row->cost_usd)->toBeNull();
});

test('AgentLoop with DatabaseSpanRecorder writes a span tree with correct parent_span_id and shared trace_id', function () {
    $callCount = 0;
    $client = Mockery::mock(AiToolCallingClient::class);
    $client->shouldReceive('chat')->twice()->andReturnUsing(function () use (&$callCount) {
        $callCount++;

        if ($callCount === 1) {
            return new AgentChatResponse(null, [new AgentToolCall('call_1', 'noop_tool', [])], promptTokens: 20, completionTokens: 5);
        }

        return new AgentChatResponse('done', promptTokens: 8, completionTokens: 3);
    });

    $tool = new class implements \App\Modules\Ai\Application\Agent\Contracts\AgentTool
    {
        public function definition(): \App\Modules\Ai\Application\Agent\Data\AgentToolDefinition
        {
            return new \App\Modules\Ai\Application\Agent\Data\AgentToolDefinition(
                'noop_tool', 'does nothing', [], \App\Modules\Ai\Application\Agent\Data\AgentToolDefinition::SIDE_EFFECT_READ_ONLY
            );
        }

        public function execute(array $arguments, AgentToolContext $context): array
        {
            return ['ok' => true];
        }
    };

    $trace = TraceContext::newTrace();
    $recorder = new DatabaseSpanRecorder;

    (new AgentLoop($client))->run(
        systemPrompt: 'system',
        startingMessages: [],
        tools: [$tool],
        maxIterations: 5,
        context: new AgentToolContext(1, 1),
        observer: new NoopLoopObserver,
        trace: $trace,
        spanRecorder: $recorder,
        turnMetadata: ['agent_type' => 'content_authoring'],
    );

    $spans = AgentTraceSpan::query()->where('trace_id', $trace->traceId)->get();

    expect($spans)->toHaveCount(4); // 1 agent_turn + 2 llm_call + 1 tool_call

    $turnSpan = $spans->firstWhere('span_type', 'agent_turn');
    expect($turnSpan)->not->toBeNull()
        ->and($turnSpan->parent_span_id)->toBeNull()
        ->and($turnSpan->status)->toBe('ok')
        ->and($turnSpan->metadata['agent_type'])->toBe('content_authoring');

    $children = $spans->where('parent_span_id', $turnSpan->span_id);
    expect($children)->toHaveCount(3);

    $llmCalls = $children->where('span_type', 'llm_call')->values();
    expect($llmCalls)->toHaveCount(2)
        ->and($llmCalls[0]->prompt_tokens)->toBe(20)
        ->and($llmCalls[0]->completion_tokens)->toBe(5)
        ->and($llmCalls[1]->prompt_tokens)->toBe(8);

    $toolCall = $children->firstWhere('span_type', 'tool_call');
    expect($toolCall->name)->toBe('noop_tool')
        ->and($toolCall->status)->toBe('ok');

    // Every span belongs to the same trace.
    expect($spans->pluck('trace_id')->unique())->toHaveCount(1);

    // Task 5.2: closing the root agent_turn span upserts a denormalized
    // agent_traces summary row, with total_cost_usd = sum of both llm_call
    // spans' cost_usd at the default config('ai.pricing') prices.
    $summary = AgentTrace::query()->where('trace_id', $trace->traceId)->firstOrFail();
    $expectedCost = $llmCalls->sum('cost_usd');
    expect($summary->entry_agent_type)->toBe('content_authoring')
        ->and($summary->status)->toBe('ok')
        ->and($summary->total_duration_ms)->toBe($turnSpan->fresh()->duration_ms)
        ->and($summary->total_cost_usd)->toBe($expectedCost)
        ->and($expectedCost)->toBeGreaterThan(0.0);
});

test('a nested agent_turn (handoff sub-agent) does not create its own agent_traces row', function () {
    $recorder = new DatabaseSpanRecorder;
    $trace = TraceContext::newTrace();

    $rootSpanId = $recorder->startSpan($trace, 'agent_turn', 'agent_loop.run', ['agent_type' => 'student_tutor']);
    $childTrace = $trace->withParentSpan($rootSpanId);

    $nestedTurnSpanId = $recorder->startSpan($childTrace, 'agent_turn', 'agent_loop.run', ['agent_type' => 'grammar_specialist']);
    $recorder->endSpan($nestedTurnSpanId, 'ok');

    // Only the root's own summary exists so far — the nested turn closing
    // must not have written a second agent_traces row for the same trace_id.
    expect(AgentTrace::query()->where('trace_id', $trace->traceId)->count())->toBe(0);

    $recorder->endSpan($rootSpanId, 'ok');

    $summaries = AgentTrace::query()->where('trace_id', $trace->traceId)->get();
    expect($summaries)->toHaveCount(1)
        ->and($summaries->first()->entry_agent_type)->toBe('student_tutor');
});
