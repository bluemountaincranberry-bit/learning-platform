<?php

namespace App\Modules\Ai\Application\Agent\Graph;

/**
 * JSON-serializable data bag nodes read and add to as a graph run
 * progresses — deliberately plain array-backed (not a typed DTO per graph)
 * because a run can be persisted mid-flight (`agent_graph_runs.state`) and
 * resumed in a completely different job/process, possibly days later
 * (`HumanCheckpointNode`, task 4.8) — see
 * docs/architecture/ai-platform-vision.md, section 7 and ADR-005. Anything
 * put in here must be `json_encode`-able.
 *
 * Also carries the pause signal a node sets to ask `GraphRunner` to stop
 * after the current step and wait for an external `resume()` — see
 * `GraphNode`'s docblock for why this lives on state rather than as a
 * separate return type.
 */
final class GraphState
{
    private bool $paused = false;

    private ?string $pauseReason = null;

    private ?string $routeTarget = null;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(private array $data = []) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Mutates and returns $this (fluent) — GraphState is a working scratchpad
     * passed through a sequence of nodes within one GraphRunner::run() call,
     * not an immutable value shared across concurrent readers.
     */
    public function set(string $key, mixed $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * Signals that GraphRunner should stop after the current node and
     * persist the run as paused, instead of continuing to the next step.
     * $reason is stored in agent_graph_runs for operator visibility (e.g.
     * "awaiting_human_review") — not shown to any end user directly.
     */
    public function pause(?string $reason = null): void
    {
        $this->paused = true;
        $this->pauseReason = $reason;
    }

    public function isPaused(): bool
    {
        return $this->paused;
    }

    public function pauseReason(): ?string
    {
        return $this->pauseReason;
    }

    /**
     * Used by `RouterNode` implementations (task 4.7) to pick the next step
     * by key instead of falling through to the definition's default
     * sequential order — a pure decision based on already-known data, no
     * LLM call and no side effect (docs/architecture/agent-framework-roadmap.md,
     * section 7). `GraphRunner` reads this via `consumeRouteTarget()` right
     * after the node returns.
     */
    public function routeTo(string $key): void
    {
        $this->routeTarget = $key;
    }

    /**
     * Reads and clears the pending route target in one step, so a stale
     * override from one node can never silently leak into how the runner
     * advances after a later node that didn't route anywhere.
     */
    public function consumeRouteTarget(): ?string
    {
        $target = $this->routeTarget;
        $this->routeTarget = null;

        return $target;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
