<?php

use App\Exceptions\AgentToolException;
use App\Modules\Ai\Application\Agent\AgentLoop;
use App\Modules\Ai\Application\Agent\Contracts\AgentLoopObserver;
use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentChatResponse;
use App\Modules\Ai\Application\Agent\Data\AgentToolCall;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\Agent\Tracing\NullSpanRecorder;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use App\Contracts\Ai\AiToolCallingClient;

/**
 * Unit-level: no Eloquent, no database, no HTTP — a fake tool-calling
 * client scripted per test, a fake in-memory tool, and a recording
 * observer. Proves AgentLoop is a pure engine that reacts only through
 * AgentLoopObserver, never touching storage itself.
 *
 * Bound to Tests\TestCase (no RefreshDatabase) purely so the Log facade
 * AgentLoop uses for the "unexpected throwable" path has a booted
 * container to resolve against — no database or Eloquent model is used
 * anywhere in this file.
 */
uses(Tests\TestCase::class);

function fakeToolContext(): AgentToolContext
{
    return new AgentToolContext(conversationId: 1, actingUserId: 1);
}

function fakeTrace(): TraceContext
{
    return TraceContext::newTrace();
}

function fakeSpanRecorder(): NullSpanRecorder
{
    return new NullSpanRecorder;
}

/**
 * Minimal in-memory AgentTool double, no Eloquent involved.
 */
class RecordingFakeTool implements AgentTool
{
    public array $calls = [];

    public function __construct(
        private readonly string $name = 'fake_tool',
        private readonly ?Closure $behavior = null,
    ) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            $this->name,
            'A fake tool for tests.',
            ['type' => 'object', 'properties' => []],
            AgentToolDefinition::SIDE_EFFECT_READ_ONLY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        $this->calls[] = $arguments;

        if ($this->behavior !== null) {
            return ($this->behavior)($arguments, $context);
        }

        return ['ok' => true];
    }
}

class RecordingObserver implements AgentLoopObserver
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

test('runs a plain text reply with no tool calls', function () {
    $client = Mockery::mock(AiToolCallingClient::class);
    $client->shouldReceive('chat')->once()->andReturn(new AgentChatResponse('hello there'));

    $observer = new RecordingObserver;

    (new AgentLoop($client))->run(
        systemPrompt: 'system',
        startingMessages: [['role' => 'user', 'content' => 'hi']],
        tools: [],
        maxIterations: 5,
        context: fakeToolContext(),
        observer: $observer,
        trace: fakeTrace(),
        spanRecorder: fakeSpanRecorder(),
    );

    expect($observer->finalResponse?->content)->toBe('hello there')
        ->and($observer->toolCalls)->toBe([])
        ->and($observer->iterationLimitReached)->toBeFalse();
});

test('runs a single tool call then replies', function () {
    $tool = new RecordingFakeTool('do_thing');

    $callCount = 0;
    $client = Mockery::mock(AiToolCallingClient::class);
    $client->shouldReceive('chat')->twice()->andReturnUsing(function () use (&$callCount) {
        $callCount++;

        if ($callCount === 1) {
            return new AgentChatResponse(null, [new AgentToolCall('call_1', 'do_thing', ['x' => 1])]);
        }

        return new AgentChatResponse('done');
    });

    $observer = new RecordingObserver;

    (new AgentLoop($client))->run('system', [], [$tool], 5, fakeToolContext(), $observer, fakeTrace(), fakeSpanRecorder());

    expect($tool->calls)->toBe([['x' => 1]])
        ->and($observer->startedToolNames)->toBe(['do_thing'])
        ->and($observer->toolCalls)->toHaveCount(1)
        ->and($observer->toolCalls[0][0]->name)->toBe('do_thing')
        ->and($observer->toolCalls[0][1])->toBe(['ok' => true])
        ->and($observer->finalResponse?->content)->toBe('done');
});

test('chains several tool calls across iterations', function () {
    $tool = new RecordingFakeTool('step');

    $callCount = 0;
    $client = Mockery::mock(AiToolCallingClient::class);
    $client->shouldReceive('chat')->times(3)->andReturnUsing(function () use (&$callCount) {
        $callCount++;

        if ($callCount <= 2) {
            return new AgentChatResponse(null, [new AgentToolCall("call_{$callCount}", 'step', ['n' => $callCount])]);
        }

        return new AgentChatResponse('all steps done');
    });

    $observer = new RecordingObserver;

    (new AgentLoop($client))->run('system', [], [$tool], 5, fakeToolContext(), $observer, fakeTrace(), fakeSpanRecorder());

    expect($tool->calls)->toBe([['n' => 1], ['n' => 2]])
        ->and($observer->toolCalls)->toHaveCount(2)
        ->and($observer->finalResponse?->content)->toBe('all steps done');
});

test('a tool throwing AgentToolException is fed back as an error result, loop continues', function () {
    $tool = new RecordingFakeTool('risky', function () {
        throw new AgentToolException('bad input');
    });

    $callCount = 0;
    $client = Mockery::mock(AiToolCallingClient::class);
    $client->shouldReceive('chat')->twice()->andReturnUsing(function () use (&$callCount) {
        $callCount++;

        if ($callCount === 1) {
            return new AgentChatResponse(null, [new AgentToolCall('call_1', 'risky', [])]);
        }

        return new AgentChatResponse('handled the error');
    });

    $observer = new RecordingObserver;

    (new AgentLoop($client))->run('system', [], [$tool], 5, fakeToolContext(), $observer, fakeTrace(), fakeSpanRecorder());

    expect($observer->toolCalls)->toHaveCount(1)
        ->and($observer->toolCalls[0][1])->toBe(['error' => 'bad input'])
        ->and($observer->finalResponse?->content)->toBe('handled the error');
});

test('an unexpected Throwable in a tool is caught and reported as a generic error, not propagated', function () {
    $tool = new RecordingFakeTool('crashes', function () {
        throw new RuntimeException('boom');
    });

    $callCount = 0;
    $client = Mockery::mock(AiToolCallingClient::class);
    $client->shouldReceive('chat')->twice()->andReturnUsing(function () use (&$callCount) {
        $callCount++;

        if ($callCount === 1) {
            return new AgentChatResponse(null, [new AgentToolCall('call_1', 'crashes', [])]);
        }

        return new AgentChatResponse('recovered');
    });

    $observer = new RecordingObserver;

    (new AgentLoop($client))->run('system', [], [$tool], 5, fakeToolContext(), $observer, fakeTrace(), fakeSpanRecorder());

    expect($observer->toolCalls[0][1])->toBe(['error' => 'Internal error running this tool.'])
        ->and($observer->finalResponse?->content)->toBe('recovered');
});

test('calling an unknown tool name returns an error result without throwing', function () {
    $callCount = 0;
    $client = Mockery::mock(AiToolCallingClient::class);
    $client->shouldReceive('chat')->twice()->andReturnUsing(function () use (&$callCount) {
        $callCount++;

        if ($callCount === 1) {
            return new AgentChatResponse(null, [new AgentToolCall('call_1', 'nope', [])]);
        }

        return new AgentChatResponse('ok');
    });

    $observer = new RecordingObserver;

    (new AgentLoop($client))->run('system', [], [], 5, fakeToolContext(), $observer, fakeTrace(), fakeSpanRecorder());

    expect($observer->toolCalls[0][1])->toBe(['error' => 'Unknown tool: nope']);
});

test('stops after maxIterations and notifies the observer instead of the model', function () {
    $tool = new RecordingFakeTool('loopy');

    $client = Mockery::mock(AiToolCallingClient::class);
    $client->shouldReceive('chat')->times(3)->andReturn(
        new AgentChatResponse(null, [new AgentToolCall('call_x', 'loopy', [])])
    );

    $observer = new RecordingObserver;

    (new AgentLoop($client))->run('system', [], [$tool], 3, fakeToolContext(), $observer, fakeTrace(), fakeSpanRecorder());

    expect($observer->iterationLimitReached)->toBeTrue()
        ->and($observer->finalResponse)->toBeNull()
        ->and($observer->toolCalls)->toHaveCount(3);
});
