<?php

namespace App\Modules\Ai\Application\Agent\Data;

use RuntimeException;

/**
 * Wire-format description of a tool, sent to the model so it knows what it
 * can call and with which arguments. Kept separate from the AgentTool
 * interface so the client layer doesn't need to depend on tool execution.
 *
 * Also carries `sideEffect` — safety metadata, not sent to the model, that
 * makes "what is this tool allowed to do to real data" an explicit,
 * checkable value instead of a convention documented only in a docblock
 * (see docs/architecture/agent-framework-roadmap.md, step 5.3). There is no
 * default: every tool must state its level, so a new tool can never end up
 * wired in without one.
 */
final class AgentToolDefinition
{
    /** Only ever returns data, never writes anything. */
    public const SIDE_EFFECT_READ_ONLY = 'read_only';

    /** Writes, but only drafts/pending rows a human must still review (never the live catalog). */
    public const SIDE_EFFECT_DRAFT_ONLY = 'draft_only';

    /** Writes to the live, user-facing catalog. Must never be wired to an agent without explicit, reviewed opt-in. */
    public const SIDE_EFFECT_PUBLISH = 'publish';

    public const SIDE_EFFECTS = [
        self::SIDE_EFFECT_READ_ONLY,
        self::SIDE_EFFECT_DRAFT_ONLY,
        self::SIDE_EFFECT_PUBLISH,
    ];

    /**
     * @param  array<string, mixed>  $parameters  JSON Schema object
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $parameters,
        public readonly string $sideEffect,
    ) {
        if (! in_array($sideEffect, self::SIDE_EFFECTS, true)) {
            throw new RuntimeException(sprintf(
                'Tool "%s" declares unknown sideEffect "%s"; must be one of: %s.',
                $name,
                $sideEffect,
                implode(', ', self::SIDE_EFFECTS)
            ));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toOpenAiFormat(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->parameters,
            ],
        ];
    }

    /**
     * Severity ranking of a sideEffect level, in the same order as
     * SIDE_EFFECTS (read_only=0 ... publish=2). Used by `HandoffTool` (task
     * 4.3) to check that a handoff's own declared sideEffect is never
     * *weaker* than the widest sideEffect the target agent is allowed to
     * use — see that class's docblock and ADR-006.
     */
    public static function rank(string $sideEffect): int
    {
        $index = array_search($sideEffect, self::SIDE_EFFECTS, true);

        if ($index === false) {
            throw new RuntimeException(sprintf(
                'Cannot rank unknown sideEffect "%s"; must be one of: %s.',
                $sideEffect,
                implode(', ', self::SIDE_EFFECTS)
            ));
        }

        return $index;
    }

    /**
     * Wiring-time invariant: an agent may only be handed tools whose
     * sideEffect is within what that agent is allowed to do. Called where
     * an agent's tool list is assembled (AiServiceProvider) so a
     * misconfigured wiring throws as soon as the DI graph is built, not
     * silently at runtime when the model happens to call the tool.
     *
     * @param  array<int, AgentToolDefinition>  $definitions
     * @param  array<int, string>  $allowedSideEffects
     */
    public static function assertSideEffectsAllowed(array $definitions, array $allowedSideEffects): void
    {
        foreach ($definitions as $definition) {
            if (! in_array($definition->sideEffect, $allowedSideEffects, true)) {
                throw new RuntimeException(sprintf(
                    'Tool "%s" has sideEffect "%s" which is not allowed for this agent (allowed: %s).',
                    $definition->name,
                    $definition->sideEffect,
                    implode(', ', $allowedSideEffects)
                ));
            }
        }
    }
}
