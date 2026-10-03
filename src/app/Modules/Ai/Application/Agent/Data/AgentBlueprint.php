<?php

namespace App\Modules\Ai\Application\Agent\Data;

use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use InvalidArgumentException;

/**
 * Declarative configuration for one agent: name, system prompt, which
 * tools it may call, how many loop iterations it gets, and which
 * `AgentToolDefinition::SIDE_EFFECT_*` levels it is allowed to use.
 *
 * This is what `ContentAgentService`/`StudentTutorAgentService` hand to
 * `AgentLoop` instead of each hardcoding its own prompt/tool-list/iteration
 * constant — see docs/architecture/agent-framework-roadmap.md, step 5.4.
 * `tools` holds class-strings, not instances: the blueprint describes what
 * an agent is allowed to be built from; resolving those classes to real
 * `AgentTool` objects (and enforcing `allowedSideEffects` against their
 * actual `sideEffect`, via `AgentToolDefinition::assertSideEffectsAllowed()`)
 * stays the container's job at wiring time (`AiServiceProvider`), not this
 * value object's.
 */
final class AgentBlueprint
{
    /**
     * @param  array<int, class-string<AgentTool>>  $tools
     * @param  array<int, string>  $allowedSideEffects
     */
    public function __construct(
        public readonly string $name,
        public readonly string $systemPrompt,
        public readonly array $tools,
        public readonly int $maxIterations,
        public readonly array $allowedSideEffects,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('AgentBlueprint requires a non-empty name.');
        }

        if (trim($systemPrompt) === '') {
            throw new InvalidArgumentException(sprintf('AgentBlueprint "%s" requires a non-empty systemPrompt.', $name));
        }

        if ($tools === []) {
            throw new InvalidArgumentException(sprintf('AgentBlueprint "%s" requires at least one tool.', $name));
        }

        foreach ($tools as $tool) {
            if (! is_string($tool) || ! is_a($tool, AgentTool::class, true)) {
                throw new InvalidArgumentException(sprintf(
                    'AgentBlueprint "%s": "%s" must be a class-string implementing %s.',
                    $name,
                    is_string($tool) ? $tool : gettype($tool),
                    AgentTool::class
                ));
            }
        }

        if ($maxIterations < 1) {
            throw new InvalidArgumentException(sprintf('AgentBlueprint "%s" requires maxIterations >= 1.', $name));
        }

        if ($allowedSideEffects === []) {
            throw new InvalidArgumentException(sprintf('AgentBlueprint "%s" requires at least one allowed sideEffect.', $name));
        }

        foreach ($allowedSideEffects as $sideEffect) {
            if (! in_array($sideEffect, AgentToolDefinition::SIDE_EFFECTS, true)) {
                throw new InvalidArgumentException(sprintf(
                    'AgentBlueprint "%s": unknown sideEffect "%s"; must be one of: %s.',
                    $name,
                    $sideEffect,
                    implode(', ', AgentToolDefinition::SIDE_EFFECTS)
                ));
            }
        }
    }
}
