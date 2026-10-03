<?php

use App\Modules\Ai\Application\Agent\AgentLoop;
use App\Modules\Ai\Application\Agent\Contracts\AgentLoopObserver;
use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentChatResponse;
use App\Modules\Ai\Application\Agent\Data\AgentToolCall;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\Agent\Tracing\NullSpanRecorder;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use App\Contracts\Ai\AiStreamingChatClient;
use App\Contracts\Ai\AiToolCallingClient;

/**
 * Unit-level, same shape as AgentLoopTest (task 3.6's streaming
 * counterpart): a fake AiStreamingChatClient scripted per test, no Eloquent.
 */
uses(Tests\TestCase::class);

class StreamingRecordingObserver implements AgentLoopObserver
{
    /** @var array<int, string> */
    public array $startedToolNames = [];

    /** @var array<int, array{0: AgentToolCall, 1: array<string,mixed>, 2: int}> */
    public array $toolCalls = [];

    public ?AgentChatResponse $finalResponse = null;

    public bool $iterationLimitReached = false;

    public function onToolCallStarted(AgentToolCall $call): void
    {
        $this->startedToolNames[] = $call->name;
    }

    public function onToolCallCompleted(AgentToolCall $call, array $result, int $latencyMs): void
    {
        $this->toolCalls[] = [$call, $result, $latencyMs];
    }

    public function onFinalResponse(AgentChatResponse $response): void
    {
        $this->finalResponse = $response;
    }

    public function onIterationLimitReached(): void
    {
        $this->iterationLimitReached = true;
    }
}

class StreamingFakeTool implements AgentTool
{
    public array $calls = [];

    public function __construct(private readonly string $name = 'fake_tool') {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            $this->name,
            'A fake tool for streaming tests.',
            ['type' => 'object', 'properties' => []],
            AgentToolDefinition::SIDE_EFFECT_READ_ONLY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        $this->calls[] = $arguments;

        return ['ok' => true];
    }
}

test('runStreaming forwards deltas to the callback and reports the final response', function () {
    $streamingClient = Mockery::mock(AiStreamingChatClient::class);
    $streamingClient->shouldReceive('chatStream')->once()->andReturnUsing(
        function ($messages, $tools, $onDelta) {
            $onDelta('Hel');
            $onDelta('lo');

            return new AgentChatResponse('Hello');
        }
    );

    $observer = new StreamingRecordingObserver;
    $deltas = [];

    (new AgentLoop(Mockery::mock(AiToolCallingClient::class), $streamingClient))->runStreaming(
        systemPrompt: 'system',
        startingMessages: [['role' => 'user', 'content' => 'hi']],
        tools: [],
        maxIterations: 5,
        context: new AgentToolContext(1, 1),
        observer: $observer,
        trace: TraceContext::newTrace(),
        spanRecorder: new NullSpanRecorder,
        onDelta: function (string $delta) use (&$deltas) { $deltas[] = $delta; },
    );

    expect($deltas)->toBe(['Hel', 'lo'])
        ->and($observer->finalResponse?->content)->toBe('Hello');
});

test('runStreaming resolves a tool call before streaming the final reply', function () {
    $tool = new StreamingFakeTool('do_thing');

    $callCount = 0;
    $streamingClient = Mockery::mock(AiStreamingChatClient::class);
    $streamingClient->shouldReceive('chatStream')->twice()->andReturnUsing(
        function ($messages, $tools, $onDelta) use (&$callCount) {
            $callCount++;

            if ($callCount === 1) {
                return new AgentChatResponse(null, [new AgentToolCall('call_1', 'do_thing', ['x' => 1])]);
            }

            $onDelta('done');

            return new AgentChatResponse('done');
        }
    );

    $observer = new StreamingRecordingObserver;

    (new AgentLoop(Mockery::mock(AiToolCallingClient::class), $streamingClient))->runStreaming(
        systemPrompt: 'system',
        startingMessages: [],
        tools: [$tool],
        maxIterations: 5,
        context: new AgentToolContext(1, 1),
        observer: $observer,
        trace: TraceContext::newTrace(),
        spanRecorder: new NullSpanRecorder,
        onDelta: function () {},
    );

    expect($tool->calls)->toBe([['x' => 1]])
        ->and($observer->startedToolNames)->toBe(['do_thing'])
        ->and($observer->toolCalls)->toHaveCount(1)
        ->and($observer->finalResponse?->content)->toBe('done');
});

test('runStreaming throws when no streaming client was wired', function () {
    (new AgentLoop(Mockery::mock(AiToolCallingClient::class)))->runStreaming(
        systemPrompt: 'system',
        startingMessages: [],
        tools: [],
        maxIterations: 5,
        context: new AgentToolContext(1, 1),
        observer: new StreamingRecordingObserver,
        trace: TraceContext::newTrace(),
        spanRecorder: new NullSpanRecorder,
        onDelta: function () {},
    );
})->throws(RuntimeException::class, 'requires a streaming-capable client');

test('runStreaming stops after maxIterations and notifies the observer', function () {
    $tool = new StreamingFakeTool('loopy');

    $streamingClient = Mockery::mock(AiStreamingChatClient::class);
    $streamingClient->shouldReceive('chatStream')->times(3)->andReturn(
        new AgentChatResponse(null, [new AgentToolCall('call_x', 'loopy', [])])
    );

    $observer = new StreamingRecordingObserver;

    (new AgentLoop(Mockery::mock(AiToolCallingClient::class), $streamingClient))->runStreaming(
        systemPrompt: 'system',
        startingMessages: [],
        tools: [$tool],
        maxIterations: 3,
        context: new AgentToolContext(1, 1),
        observer: $observer,
        trace: TraceContext::newTrace(),
        spanRecorder: new NullSpanRecorder,
        onDelta: function () {},
    );

    expect($observer->iterationLimitReached)->toBeTrue()
        ->and($observer->finalResponse)->toBeNull();
});
