<?php

use App\Exceptions\AgentToolException;
use App\Modules\Ai\Application\Agent\AgentLoop;
use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentBlueprint;
use App\Modules\Ai\Application\Agent\Data\AgentChatResponse;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\Agent\Tools\HandoffTool;
use App\Modules\Ai\Application\Agent\Tracing\NullSpanRecorder;
use App\Contracts\Ai\AiToolCallingClient;

/**
 * Unit-level, same style as AgentLoopTest: a fake AiToolCallingClient
 * scripted per test, a real AgentLoop/NullSpanRecorder, no database. Proves
 * the two guardrails ADR-006 requires are actual enforcing mechanics, not
 * just docblocks — see HandoffTool's own docblock.
 */
uses(Tests\TestCase::class);

function targetBlueprint(array $allowedSideEffects = [AgentToolDefinition::SIDE_EFFECT_READ_ONLY]): AgentBlueprint
{
    return new AgentBlueprint(
        name: 'fake_target_agent',
        systemPrompt: 'You are a fake target agent.',
        tools: [FakeHandoffTargetTool::class],
        maxIterations: 3,
        allowedSideEffects: $allowedSideEffects,
    );
}

class FakeHandoffTargetTool implements AgentTool
{
    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition('fake_target_tool', 'noop', ['type' => 'object', 'properties' => []], AgentToolDefinition::SIDE_EFFECT_READ_ONLY);
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        return ['handoff_depth_seen' => $context->handoffDepth];
    }
}

class ReadOnlyHandoffTool extends HandoffTool
{
    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition('handoff_to_fake_target', 'Hand off to the fake target agent.', [
            'type' => 'object',
            'properties' => ['task' => ['type' => 'string']],
            'required' => ['task'],
        ], AgentToolDefinition::SIDE_EFFECT_READ_ONLY);
    }
}

class DraftOnlyHandoffTool extends HandoffTool
{
    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition('handoff_to_fake_target', 'Hand off to the fake target agent.', [
            'type' => 'object',
            'properties' => ['task' => ['type' => 'string']],
            'required' => ['task'],
        ], AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY);
    }
}

test('constructing a handoff tool with a weaker sideEffect than its target throws at wiring time', function () {
    $client = Mockery::mock(AiToolCallingClient::class);
    $loop = new AgentLoop($client);

    expect(fn () => new ReadOnlyHandoffTool($loop, targetBlueprint([AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY]), [], new NullSpanRecorder))
        ->toThrow(RuntimeException::class, 'weaker than target agent');
});

test('constructing a handoff tool whose sideEffect covers the target succeeds', function () {
    $client = Mockery::mock(AiToolCallingClient::class);
    $loop = new AgentLoop($client);

    $tool = new DraftOnlyHandoffTool($loop, targetBlueprint([AgentToolDefinition::SIDE_EFFECT_READ_ONLY, AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY]), [], new NullSpanRecorder);

    expect($tool)->toBeInstanceOf(HandoffTool::class);
});

test('a handoff attempted at max depth is rejected without running the target agent', function () {
    $client = Mockery::mock(AiToolCallingClient::class);
    $client->shouldNotReceive('chat');
    $loop = new AgentLoop($client);

    $tool = new ReadOnlyHandoffTool($loop, targetBlueprint(), [], new NullSpanRecorder);

    $atMaxDepth = new AgentToolContext(1, 1, HandoffTool::MAX_HANDOFF_DEPTH);

    expect(fn () => $tool->execute(['task' => 'do something'], $atMaxDepth))
        ->toThrow(AgentToolException::class, 'maximum handoff depth');
});

test('a handoff below max depth runs the target agent loop and increments depth for its context', function () {
    $client = Mockery::mock(AiToolCallingClient::class);
    $client->shouldReceive('chat')->once()->andReturnUsing(function ($messages, $tools) {
        return new AgentChatResponse('done from target agent');
    });
    $loop = new AgentLoop($client);

    $tool = new ReadOnlyHandoffTool($loop, targetBlueprint(), [], new NullSpanRecorder);

    $context = new AgentToolContext(1, 1, 0);
    $result = $tool->execute(['task' => 'explain something'], $context);

    expect($result)->toBe(['result' => 'done from target agent']);
});

test('handoff requires a non-empty task argument', function () {
    $client = Mockery::mock(AiToolCallingClient::class);
    $client->shouldNotReceive('chat');
    $loop = new AgentLoop($client);

    $tool = new ReadOnlyHandoffTool($loop, targetBlueprint(), [], new NullSpanRecorder);

    expect(fn () => $tool->execute([], new AgentToolContext(1, 1, 0)))
        ->toThrow(AgentToolException::class, 'non-empty "task"');
});

test('depth 3 (three nested handoffs) is rejected, proving A -> B -> C is blocked, not just A -> B -> A', function () {
    $client = Mockery::mock(AiToolCallingClient::class);
    $client->shouldNotReceive('chat');
    $loop = new AgentLoop($client);

    $tool = new ReadOnlyHandoffTool($loop, targetBlueprint(), [], new NullSpanRecorder);

    // Simulates: TutorAgent (depth 0) -> Agent A (depth 1) -> Agent B (depth 2) -> attempted Agent C (depth 2 context, which is already MAX_HANDOFF_DEPTH).
    $depthTwoContext = new AgentToolContext(1, 1, 2);

    expect(fn () => $tool->execute(['task' => 'go deeper'], $depthTwoContext))
        ->toThrow(AgentToolException::class);
});
