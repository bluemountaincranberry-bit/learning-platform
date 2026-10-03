<?php

namespace App\Modules\Ai\Application\Agent\Graph;

use App\Modules\Ai\Domain\Models\AgentGraphRun;
use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Ai\Application\Agent\Graph\Definitions\AiAnalysisGraph;

/**
 * Thin, Eloquent-owning wrapper around `GraphRunner` for `AiAnalysisGraph`
 * (task 4.2) — the graph-engine analogue of `ContentAgentService` wrapping
 * `AgentLoop`. Deliberately **not** wired into `RunAiContentAnalysisJob` or
 * `ApplyAiCandidatesAction` yet: those two already run this exact pipeline
 * in production today, and per this epic's explicit guardrail ("treat
 * AiAnalysisRun with the same care EPIC 1 treated ContentAgentService —
 * characterization tests before refactoring anything it depends on"),
 * swapping the live entry points to run through a brand-new engine is a
 * separate, higher-risk change this task does not make. This class is an
 * additive, fully-tested proof that the graph re-expression is correct,
 * available for a future task to switch the real entry points to once it
 * has run alongside the existing pipeline with confidence — see this
 * epic's final report for the explicit call-out.
 */
final class AiAnalysisGraphService
{
    public function __construct(
        private readonly GraphRunner $runner,
        private readonly GraphDefinitionResolver $resolver,
    ) {}

    /**
     * Runs analyze -> match, then the `review_checkpoint`
     * `HumanCheckpointNode` (task 4.8) pauses the run awaiting human review
     * (mirrors "admin reviews pending candidates before clicking Apply"
     * today).
     *
     * Task 8.2: also drives `AiAnalysisRun.status`, exactly like
     * `RunAiContentAnalysisJob` drives it on the live path
     * (`pending -> running -> completed/failed`) — the admin-facing status
     * badge (`ContentInfolist.php`) reads this column regardless of which
     * pipeline produced the run. Pausing at `review_checkpoint` is mapped to
     * `completed`: that pause *is* "ready for review" in this graph, the
     * same meaning `completed` already has on the live path (set right
     * after best-effort matching, before any human has reviewed or applied
     * anything). `approveAndResume()` below deliberately does not set
     * `completed` again — matching `AiCandidateApplyService::apply()` never
     * touching this column on the live path either.
     */
    public function start(AiAnalysisRun $analysisRun): GraphRunResult
    {
        $resolved = $this->resolver->resolve(AiAnalysisGraph::NAME);
        $definition = $resolved->definition;
        $state = new GraphState(['ai_analysis_run_id' => $analysisRun->id]);

        $analysisRun->update([
            'status' => AiAnalysisRun::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $observer = EloquentGraphRunObserver::start($definition->name, $state, $definition->firstStep()->key, $resolved->versionId);

        $result = $this->runner->run($definition, $state, $observer);

        if ($result->status === GraphRunResult::STATUS_FAILED) {
            $analysisRun->update([
                'status' => AiAnalysisRun::STATUS_FAILED,
                'completed_at' => now(),
                'failure_reason' => $result->failureReason,
            ]);
        } else {
            // STATUS_PAUSED (at review_checkpoint, "ready for review") and
            // STATUS_COMPLETED both mean the same thing for AiAnalysisRun:
            // the graph is done doing anything that requires no further
            // human input right now.
            $analysisRun->update([
                'status' => AiAnalysisRun::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        }

        return $result;
    }

    /**
     * Resumes a paused run with `approved = true`, letting `ApplyNode`
     * actually apply the (by now human-reviewed) candidates.
     */
    public function approveAndResume(int $graphRunId): GraphRunResult
    {
        $observer = EloquentGraphRunObserver::resume($graphRunId);
        $dbRun = $observer->run();

        // Deliberately re-resolves rather than reusing whatever version
        // started this run: if an admin published a new version of this
        // graph while the run sat paused, resuming picks it up — same
        // "always the current published version" behavior a fresh start()
        // would have. Revisit if that turns out to be surprising in
        // practice (pin to `$dbRun->graph_definition_version_id` instead).
        $resolved = $this->resolver->resolve($dbRun->graph_name);
        $state = GraphState::fromArray($dbRun->state ?? [])->set('approved', true);

        return $this->runner->run($resolved->definition, $state, $observer, $dbRun->current_node);
    }

    public function latestRunFor(AiAnalysisRun $analysisRun): ?AgentGraphRun
    {
        return AgentGraphRun::query()
            ->where('graph_name', AiAnalysisGraph::NAME)
            ->where('state->ai_analysis_run_id', $analysisRun->id)
            ->latest('id')
            ->first();
    }
}
