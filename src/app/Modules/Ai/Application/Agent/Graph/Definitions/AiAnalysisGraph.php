<?php

namespace App\Modules\Ai\Application\Agent\Graph\Definitions;

use App\Modules\Ai\Application\Agent\Graph\GraphDefinition;
use App\Modules\Ai\Application\Agent\Graph\GraphNodeRegistry;

/**
 * First real `GraphDefinition` (task 4.2) — a re-expression of the
 * already-existing, already-understood `AiAnalysisRun` pipeline (`analyze`
 * -> `match` -> review checkpoint -> `apply`, currently split across
 * `RunAiContentAnalysisJob` and the admin's manual "Apply approved AI
 * candidates" click) as explicit graph steps, proving `GraphRunner` works
 * on a known process before task 4.7 builds a graph for something new
 * (`TutorRoutingGraph`) — see
 * docs/architecture/agent-framework-roadmap.md, section 7 ("Первый
 * полигон").
 *
 * The `review_checkpoint` step (task 4.8) is a standalone
 * `HumanCheckpointNode` — before that task, this graph paused inside
 * `ApplyNode` itself; see that class's docblock for the refactor.
 *
 * As of task 4.10, this is a **data**-driven definition: `stepDefinitions()`
 * is a plain array of `{key, node}` pairs, and `definition()` resolves each
 * step's node through `GraphNodeRegistry` (`config('ai.graph.node_registry')`)
 * instead of this class hand-building `AnalyzeNode`/`MatchNode`/etc.
 * instances itself — the conversion task 4.10 asked for, on the graph that
 * already proved the engine (task 4.2), without changing what the graph
 * actually does (`AiAnalysisGraphServiceTest` is unchanged behaviorally).
 */
final class AiAnalysisGraph
{
    public const NAME = 'ai_analysis';

    public function __construct(private readonly GraphNodeRegistry $registry) {}

    /**
     * @return array<int, array{key: string, node: string}>
     */
    public static function stepDefinitions(): array
    {
        return [
            ['key' => 'analyze', 'node' => 'analyze'],
            ['key' => 'match', 'node' => 'match'],
            ['key' => 'review_checkpoint', 'node' => 'human_checkpoint'],
            ['key' => 'apply', 'node' => 'apply'],
        ];
    }

    public function definition(): GraphDefinition
    {
        return $this->registry->buildDefinition(self::NAME, self::stepDefinitions());
    }
}
