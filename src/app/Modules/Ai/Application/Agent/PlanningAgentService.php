<?php

namespace App\Modules\Ai\Application\Agent;

use App\Modules\Ai\Application\Agent\Graph\Definitions\StudyPlanGraph;
use App\Modules\Ai\Application\Agent\Graph\EloquentGraphRunObserver;
use App\Modules\Ai\Application\Agent\Graph\GraphDefinitionResolver;
use App\Modules\Ai\Application\Agent\Graph\GraphRunner;
use App\Modules\Ai\Application\Agent\Graph\GraphRunResult;
use App\Modules\Ai\Application\Agent\Graph\GraphState;

/**
 * Task 4.12 — the composite "study plan" scenario
 * (`ai-platform-vision.md` section 6's IELTS-prep example) implemented on
 * top of `ParallelNode`/`StudyPlanGraph` (task 4.11), not as
 * `PlanningAgentService` model-directing a chain of `HandoffTool` calls —
 * see `StudyPlanGraph`'s docblock for why (ADR-006's "graduate to
 * Workflow" rule).
 *
 * **Deliberately has no `AgentLoop`/`AgentBlueprint`/tools of its own.**
 * The whole point of graduating this composition to a graph is that
 * deciding "who handles this" no longer needs an LLM call at all — the
 * structure (fan out to grammar + review specialists, merge) is fixed and
 * known in advance, so there is nothing left for a top-level reasoning
 * loop to decide. If a future scenario genuinely needs an LLM to choose
 * *which* specialists to include per request (not just run all of them),
 * that decision point would earn `PlanningAgentService` a real
 * `AgentBlueprint` — not before there's a concrete need for it (ADR-002).
 *
 * Thin, Eloquent-touching wrapper around `GraphRunner`, the same shape as
 * `AiAnalysisGraphService` (task 4.2): `start()`/`resume()` create/reload
 * the `AgentGraphRun` row and drive the graph via `GraphRunner`. Because
 * `StudyPlanGraph`'s first step is a `ParallelNode`, a real call to
 * `start()` in production returns a `paused` result almost immediately
 * (branches run on Horizon workers); the caller (e.g. a future
 * student-facing endpoint) is expected to poll or be notified once
 * `ResumeGraphJob` completes the run — this class does not itself block
 * waiting for that, matching `ParallelNode`'s own async design.
 */
final class PlanningAgentService
{
    public function __construct(
        private readonly GraphRunner $runner,
        private readonly GraphDefinitionResolver $resolver,
    ) {}

    public function start(int $actingUserId, string $goal): GraphRunResult
    {
        $resolved = $this->resolver->resolve(StudyPlanGraph::NAME);
        $definition = $resolved->definition;
        $state = new GraphState(['acting_user_id' => $actingUserId, 'task' => $goal]);

        $observer = EloquentGraphRunObserver::start($definition->name, $state, $definition->firstStep()->key, $resolved->versionId);
        $state->set('graph_run_id', $observer->run()->id);

        return $this->runner->run($definition, $state, $observer);
    }

    public function resume(int $graphRunId): GraphRunResult
    {
        $observer = EloquentGraphRunObserver::resume($graphRunId);
        $dbRun = $observer->run();

        $resolved = $this->resolver->resolve($dbRun->graph_name);
        $state = GraphState::fromArray($dbRun->state ?? []);

        return $this->runner->run($resolved->definition, $state, $observer, $dbRun->current_node);
    }
}
