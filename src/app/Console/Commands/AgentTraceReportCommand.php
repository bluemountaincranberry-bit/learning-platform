<?php

namespace App\Console\Commands;

use App\Modules\Ai\Domain\Models\AgentTraceSpan;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Minimal, real reporting on top of Tier 1 tracing (task 1.7):
 * reconstruct the span tree for one trace (debugging a single turn), or
 * summarize token usage/estimated cost per agent_type over the last week
 * (unit-economics — see docs/architecture/agent-framework-roadmap.md,
 * section 9). No new infrastructure: both read straight from
 * `agent_trace_spans`.
 */
class AgentTraceReportCommand extends Command
{
    protected $signature = 'ai:agent-trace-report
                            {--trace= : Print the span tree for a specific trace_id}
                            {--weekly : Print tokens/estimated cost per agent_type over the last 7 days}';

    protected $description = 'Report on recorded agent_trace_spans: a span tree for one trace, or a weekly token/cost summary by agent_type.';

    public function handle(): int
    {
        if ($traceId = $this->option('trace')) {
            return $this->printTraceTree($traceId);
        }

        if ($this->option('weekly')) {
            return $this->printWeeklySummary();
        }

        $this->error('Specify --trace=<trace_id> or --weekly.');

        return self::FAILURE;
    }

    private function printTraceTree(string $traceId): int
    {
        $spans = AgentTraceSpan::query()
            ->where('trace_id', $traceId)
            ->orderBy('started_at')
            ->get();

        if ($spans->isEmpty()) {
            $this->warn("No spans found for trace_id {$traceId}.");

            return self::FAILURE;
        }

        $byParent = $spans->groupBy('parent_span_id');
        $roots = $spans->filter(fn (AgentTraceSpan $span) => $span->parent_span_id === null);

        foreach ($roots as $root) {
            $this->printNode($root, $byParent, 0);
        }

        return self::SUCCESS;
    }

    /**
     * @param  Collection<string, Collection<int, AgentTraceSpan>>  $byParent
     */
    private function printNode(AgentTraceSpan $span, Collection $byParent, int $depth): void
    {
        $indent = str_repeat('  ', $depth);
        $duration = $span->duration_ms !== null ? "{$span->duration_ms}ms" : 'unfinished';
        $status = $span->status ?? 'running';

        $this->line("{$indent}- [{$span->span_type}] {$span->name} ({$status}, {$duration})");

        foreach ($byParent->get($span->span_id, collect()) as $child) {
            $this->printNode($child, $byParent, $depth + 1);
        }
    }

    private function printWeeklySummary(): int
    {
        $turns = AgentTraceSpan::query()
            ->where('span_type', AgentTraceSpan::SPAN_TYPE_AGENT_TURN)
            ->where('started_at', '>=', now()->subDays(7))
            ->get();

        if ($turns->isEmpty()) {
            $this->info('No agent turns recorded in the last 7 days.');

            return self::SUCCESS;
        }

        $llmCallsByTrace = AgentTraceSpan::query()
            ->where('span_type', AgentTraceSpan::SPAN_TYPE_LLM_CALL)
            ->whereIn('trace_id', $turns->pluck('trace_id')->unique())
            ->get()
            ->groupBy('trace_id');

        $promptPrice = (float) config('ai.pricing.prompt_per_1k_usd', 0);
        $completionPrice = (float) config('ai.pricing.completion_per_1k_usd', 0);

        $rows = $turns
            ->groupBy(fn (AgentTraceSpan $turn) => $turn->metadata['agent_type'] ?? 'unknown')
            ->map(function (Collection $turnsForAgent, string $agentType) use ($llmCallsByTrace, $promptPrice, $completionPrice) {
                $promptTokens = 0;
                $completionTokens = 0;

                foreach ($turnsForAgent as $turn) {
                    foreach ($llmCallsByTrace->get($turn->trace_id, collect()) as $call) {
                        $promptTokens += $call->prompt_tokens ?? 0;
                        $completionTokens += $call->completion_tokens ?? 0;
                    }
                }

                $cost = ($promptTokens / 1000 * $promptPrice) + ($completionTokens / 1000 * $completionPrice);

                return [$agentType, $turnsForAgent->count(), $promptTokens, $completionTokens, number_format($cost, 4)];
            })
            ->values()
            ->all();

        $this->table(['agent_type', 'turns', 'prompt_tokens', 'completion_tokens', 'estimated_cost_usd'], $rows);

        return self::SUCCESS;
    }
}
