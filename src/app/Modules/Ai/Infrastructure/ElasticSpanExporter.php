<?php

namespace App\Modules\Ai\Infrastructure;

use App\Modules\Ai\Domain\Models\AgentTraceSpan;

/**
 * Task 5.3 — Tier 2 tracing (agent-framework-roadmap.md section 9, "Tier 2
 * — when appears real load/several live agents, not before"). Ships closed
 * `agent_trace_spans` rows (task 1.7, the source of truth) into a plain
 * Elasticsearch index (config('elasticsearch.trace_spans')) for
 * Kibana Discover-style search once ad-hoc SQL against Postgres stops being
 * a convenient way to explore traces — no OpenTelemetry SDK, no
 * Jaeger/Tempo: this is the "simple indexation of agent_trace_spans into an
 * ES index" option the docs explicitly chose over adopting a new tracing
 * framework, using the same thin `ElasticsearchClient` wrapper as the RAG
 * corpus (task 2.2) rather than a second, incompatible ES client (see that
 * class's docblock).
 *
 * A no-op when `elasticsearch.enabled` is false — same safe-default pattern
 * as `RagIndexingService`.
 */
class ElasticSpanExporter
{
    public function __construct(
        private readonly ElasticsearchClient $client,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('elasticsearch.enabled', false);
    }

    /**
     * Creates the trace span export index with its mapping if it doesn't
     * already exist. Safe to call repeatedly (checked, not blind create).
     */
    public function ensureIndexExists(): void
    {
        $index = $this->indexName();

        if ($this->client->indexExists($index)) {
            return;
        }

        $this->client->createIndex($index, config('elasticsearch.trace_spans.mappings'));
    }

    /**
     * Exports every closed (`ended_at` set), not-yet-exported span, oldest
     * first, up to `$limit` per call — a plain paginated batch rather than
     * a queued job, matching `RagIndexingService`'s reasoning: at the
     * volume this exists for (Tier 2, only once real load exists), a single
     * synchronous pass per invocation is simpler than adding a queue for no
     * real payoff yet.
     *
     * @return int Number of spans exported.
     */
    public function exportPending(int $limit = 500): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $this->ensureIndexExists();

        $spans = AgentTraceSpan::query()
            ->whereNotNull('ended_at')
            ->whereNull('exported_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $count = 0;
        foreach ($spans as $span) {
            $this->client->indexDocument($this->indexName(), $span->span_id, [
                'trace_id' => $span->trace_id,
                'span_id' => $span->span_id,
                'parent_span_id' => $span->parent_span_id,
                'span_type' => $span->span_type,
                'name' => $span->name,
                'agent_type' => $span->metadata['agent_type'] ?? null,
                'started_at' => $span->started_at?->toIso8601String(),
                'ended_at' => $span->ended_at?->toIso8601String(),
                'duration_ms' => $span->duration_ms,
                'prompt_tokens' => $span->prompt_tokens,
                'completion_tokens' => $span->completion_tokens,
                'cost_usd' => $span->cost_usd,
                'status' => $span->status,
                'metadata' => $span->metadata,
            ]);

            $span->update(['exported_at' => now()]);
            $count++;
        }

        return $count;
    }

    private function indexName(): string
    {
        return config('elasticsearch.trace_spans.index', 'agent_trace_spans');
    }
}
