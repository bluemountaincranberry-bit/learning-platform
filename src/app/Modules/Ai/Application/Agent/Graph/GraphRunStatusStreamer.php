<?php

namespace App\Modules\Ai\Application\Agent\Graph;

use App\Modules\Ai\Domain\Models\AgentGraphRun;

/**
 * Polls `agent_graph_runs` (already the durable record of `current_node`/
 * `status` for any run — `EloquentGraphRunObserver` keeps it current on
 * every step) and hands each *changed* snapshot to $emit — the canvas's
 * live "which node is running now" highlight. Deliberately a poll on the
 * existing table rather than a new pub/sub channel: `GraphRunner` already
 * runs inside a queue worker process separate from this HTTP request, so
 * the DB row is the only thing both sides actually share (same reasoning
 * ADR-005 applies to Redis-vs-Postgres: the DB is the source of truth,
 * this just re-reads it cheaply instead of adding a broadcast layer for
 * one low-frequency status line).
 *
 * `maxIterations`/`sleepMicroseconds` are constructor-injected (not
 * hardcoded constants) so tests can bind a fast, bounded instance instead
 * of the real ~5-minute production budget (`AiServiceProvider`'s binding).
 */
final class GraphRunStatusStreamer
{
    public function __construct(
        private readonly int $maxIterations = 600,
        private readonly int $sleepMicroseconds = 500000,
    ) {}

    /**
     * @param  callable(array{node_key: ?string, status: string}): void  $emit
     */
    public function stream(int $runId, callable $emit): void
    {
        $lastEmitted = null;

        for ($i = 0; $i < $this->maxIterations; $i++) {
            if (connection_aborted()) {
                return;
            }

            $run = AgentGraphRun::query()->find($runId);

            if ($run === null) {
                $emit(['node_key' => null, 'status' => 'not_found']);

                return;
            }

            $snapshot = ['node_key' => $run->current_node, 'status' => $run->status];

            if ($snapshot !== $lastEmitted) {
                $emit($snapshot);
                $lastEmitted = $snapshot;
            }

            if (in_array($run->status, [AgentGraphRun::STATUS_COMPLETED, AgentGraphRun::STATUS_FAILED], true)) {
                return;
            }

            if ($i < $this->maxIterations - 1) {
                usleep($this->sleepMicroseconds);
            }
        }
    }
}
