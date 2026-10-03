<?php

namespace App\Modules\Ai\Application\Agent\Tools;

use App\Exceptions\AgentToolException;
use App\Modules\Ai\Application\Agent\AgentLoop;
use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentBlueprint;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\Agent\Support\NonPersistingAgentObserver;
use App\Modules\Ai\Application\Agent\Tracing\SpanRecorder;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use RuntimeException;
use Throwable;

/**
 * Base class for "hand off to another agent" tools (task 4.3) —
 * model-directed handoff, docs/architecture/agent-framework-roadmap.md,
 * section 8. From the calling `AgentLoop`'s point of view a `HandoffTool`
 * is just another `AgentTool`: `execute()` runs the target agent's
 * `AgentLoop` synchronously, in the same job, and returns its final reply
 * as an ordinary tool result — no new engine mechanics, no Kafka (ADR-003),
 * no separate job dispatch (ai-platform-vision.md, section 6: "handoff is a
 * direct, synchronous call, not through Kafka").
 *
 * Two guardrails are enforced here, not left as documentation, per ADR-006:
 *
 * 1. **Max handoff depth = 2** (`TutorAgent -> Agent A -> {Agent B, ...}`).
 *    Checked in `execute()` against `AgentToolContext::handoffDepth`, which
 *    every handoff increments for the context it hands to the target
 *    agent's nested loop — a third-level handoff throws before running
 *    anything, protecting against `A -> B -> A -> B` cycles the same way
 *    `AgentLoop::MAX_ITERATIONS` protects a single agent's own loop.
 * 2. **sideEffect cannot be weaker than the target agent's widest allowed
 *    sideEffect.** Checked once, at construction (wiring time, same
 *    principle as `AgentToolDefinition::assertSideEffectsAllowed()`): if a
 *    handoff tool declared `read_only` but its target agent is allowed
 *    `draft_only` actions, an outer agent restricted to `read_only` tools
 *    could reach a `draft_only` write through the handoff — a bypass of the
 *    exact rule task 1.3 made an invariant. This throws as soon as the
 *    handoff tool is constructed, before the app ever finishes booting.
 *
 * Concrete subclasses (e.g. a future `HandoffToGrammarAgentTool`) only need
 * to implement `definition()`; everything else is inherited.
 */
abstract class HandoffTool implements AgentTool
{
    public const MAX_HANDOFF_DEPTH = 2;

    /**
     * @param  array<int, AgentTool>  $targetTools  Resolved tool instances for $targetBlueprint->tools (same shape AiServiceProvider builds for a top-level agent).
     */
    public function __construct(
        protected readonly AgentLoop $loop,
        protected readonly AgentBlueprint $targetBlueprint,
        protected readonly array $targetTools,
        protected readonly SpanRecorder $spanRecorder,
    ) {
        $this->assertSideEffectCoversTarget();
    }

    abstract public function definition(): AgentToolDefinition;

    public function execute(array $arguments, AgentToolContext $context): array
    {
        if ($context->handoffDepth >= self::MAX_HANDOFF_DEPTH) {
            throw new AgentToolException(sprintf(
                'Cannot hand off to "%s": maximum handoff depth (%d) already reached — see ADR-006.',
                $this->targetBlueprint->name,
                self::MAX_HANDOFF_DEPTH
            ));
        }

        $task = is_string($arguments['task'] ?? null) ? trim($arguments['task']) : '';

        if ($task === '') {
            throw new AgentToolException('Handoff requires a non-empty "task" argument describing what the target agent should do.');
        }

        $childContext = new AgentToolContext(
            $context->conversationId,
            $context->actingUserId,
            $context->handoffDepth + 1,
            $context->trace,
        );

        $trace = $context->trace ?? TraceContext::newTrace();
        $handoffSpanId = $this->spanRecorder->startSpan($trace, 'handoff', $this->definition()->name, [
            'target_agent' => $this->targetBlueprint->name,
            'handoff_depth' => $childContext->handoffDepth,
        ]);
        $childTrace = $trace->withParentSpan($handoffSpanId);

        $observer = new NonPersistingAgentObserver;

        try {
            $this->loop->run(
                systemPrompt: $this->targetBlueprint->systemPrompt,
                startingMessages: [['role' => 'user', 'content' => $task]],
                tools: $this->targetTools,
                maxIterations: $this->targetBlueprint->maxIterations,
                context: $childContext,
                observer: $observer,
                trace: $childTrace,
                spanRecorder: $this->spanRecorder,
                turnMetadata: ['agent_type' => $this->targetBlueprint->name, 'handoff_depth' => $childContext->handoffDepth],
            );
        } catch (Throwable $e) {
            $this->spanRecorder->endSpan($handoffSpanId, 'error', ['error' => $e->getMessage()]);
            throw $e;
        }

        $this->spanRecorder->endSpan($handoffSpanId, 'ok');

        if ($observer->iterationLimitReached) {
            return ['result' => "{$this->targetBlueprint->name} could not finish this within its step limit."];
        }

        return ['result' => (string) $observer->finalText];
    }

    private function assertSideEffectCoversTarget(): void
    {
        $handoffRank = AgentToolDefinition::rank($this->definition()->sideEffect);
        $targetMaxRank = max(array_map(
            fn (string $effect) => AgentToolDefinition::rank($effect),
            $this->targetBlueprint->allowedSideEffects
        ));

        if ($handoffRank < $targetMaxRank) {
            throw new RuntimeException(sprintf(
                'Handoff tool "%s" declares sideEffect "%s", which is weaker than target agent "%s"\'s widest allowed sideEffect ("%s") — see ADR-006.',
                $this->definition()->name,
                $this->definition()->sideEffect,
                $this->targetBlueprint->name,
                AgentToolDefinition::SIDE_EFFECTS[$targetMaxRank]
            ));
        }
    }
}
