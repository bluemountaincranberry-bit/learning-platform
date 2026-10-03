<?php

namespace App\Modules\Ai\Application\Agent\Graph;

/**
 * Optional capability a `GraphNode` implementation can add so the
 * graph-builder canvas's node palette shows a human label/description
 * instead of the raw registry key — e.g. "human_checkpoint" becomes
 * "Human review checkpoint". Deliberately optional (not part of
 * `GraphNode` itself): a node that doesn't implement this still works
 * everywhere it already did, `GraphNodeRegistry::describeAll()` just
 * falls back to the raw key for it.
 */
interface DescribesGraphNode
{
    public static function paletteLabel(): string;

    public static function paletteDescription(): string;

    /**
     * The `prompt_templates.key` this node's LLM call resolves through
     * `PromptRegistryInterface`, or null when this node type never calls
     * an LLM at all (`MatchNode`, `HumanCheckpointNode`, `ApplyNode`) —
     * lets the graph-builder canvas offer "edit this node's prompt"
     * directly from its property panel instead of only from the separate
     * prompt-template screen. A `GrammarAgentGraphNode`/`ReviewAgentGraphNode`
     * returns the *agent's* key (`agent_{type}_system_prompt`,
     * `AiServiceProvider::resolveBlueprintSystemPrompt()`) — editing it
     * from the canvas changes that shared agent's prompt everywhere it's
     * used, not just this one graph step, which the canvas UI must make
     * clear rather than implying a node-scoped override.
     */
    public static function promptKey(): ?string;

    /**
     * See `GraphNodeContract`'s own docblock for the full rationale — in
     * short, this is the answer to "what does this node actually do to
     * `GraphState` and the outside world", declared by hand rather than
     * inferred, so the builder canvas can show it instead of a bare class
     * name that looks the same for every node type.
     */
    public static function nodeContract(): GraphNodeContract;
}
