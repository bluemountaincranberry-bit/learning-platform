<?php

namespace App\Modules\Ai\Application\Agent\Graph;

use App\Modules\Ai\Application\Data\GraphTestRunResult;
use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Ai\Domain\Models\PersistedGraphDefinitionVersion;
use App\Modules\Content\Application\Contracts\GraphTestContentFactoryInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * "Test run this graph version" — the graph-level counterpart of
 * `PromptTemplateAdminService::testRun()`, for the same reason: an admin
 * editing a draft's node wiring (not just one node's prompt wording) has
 * no way to see whether it actually produces sensible output before
 * publishing it live. Scoped to `ai_analysis`-shaped graphs specifically
 * (content in, lexeme/grammar candidates out) — the only graph with real
 * nodes today; a structurally different future graph would need its own
 * test-run entry point, not a generic "feed it any input" shape, because
 * there is no generic notion of "the input" a graph takes.
 *
 * Safety, two independent layers (this graph's nodes have real database
 * side effects unlike a bare LLM call, so "just call the API and discard
 * the text" isn't enough here):
 *
 * 1. **DB transaction, always rolled back.** `AnalyzeNode`/`MatchNode`
 *    write real `ContentLexemeCandidate`/`ContentGrammarCandidate` rows
 *    (via a real, throwaway `Content`/`AiAnalysisRun` created for this
 *    call only) — reading them back before the rollback, then discarding
 *    the transaction, undoes all of that with no residue. This alone
 *    would NOT be enough for `ApplyNode`, whose
 *    `AiCandidateApplyService::apply()` dispatches queued jobs
 *    (`ComputeGrammarRuleEmbeddingsJob`, lexeme-sync) that live outside
 *    Postgres and survive a rollback — hence layer 2.
 * 2. **`GraphState::get('test_mode') === true`**, checked by `ApplyNode`
 *    itself (see that class's docblock) — a structural guarantee that
 *    holds regardless of whether the draft's wiring happens to keep a
 *    `HumanCheckpointNode` in front of `ApplyNode`. Any future node with
 *    a real-world side effect a DB rollback can't undo must add the same
 *    check.
 *
 * `ParallelNode` (fan-out via real `Bus::batch()` jobs in a separate
 * process/connection) cannot appear in a canvas-built definition at all
 * today — it has no `node_registry` key (its constructor takes a
 * `$branches` array the container can't resolve; `StudyPlanGraph` builds
 * it by hand in PHP, not through `GraphNodeRegistry`). If a future task
 * adds one, this method's DB-transaction-rollback approach would silently
 * break for it (the batch's jobs run asynchronously, after the rollback
 * already happened) — whoever adds that registry key must revisit this
 * class then, not before.
 */
final class GraphDefinitionTestRunService
{
    public function __construct(
        private readonly GraphNodeRegistry $nodeRegistry,
        private readonly GraphRunner $runner,
        private readonly GraphTestContentFactoryInterface $testContent,
    ) {}

    /**
     * @throws InvalidArgumentException if $versionId does not belong to $graphKey, or the version's nodes/edges/branches are not runnable
     */
    public function testRun(string $graphKey, int $versionId, string $testTranscript, string $sourceLanguage = 'en'): GraphTestRunResult
    {
        $version = PersistedGraphDefinitionVersion::query()
            ->whereHas('graphDefinition', fn ($q) => $q->where('key', $graphKey))
            ->find($versionId);

        if ($version === null) {
            throw new InvalidArgumentException("Version {$versionId} does not belong to graph definition \"{$graphKey}\".");
        }

        $definition = $this->nodeRegistry->buildDefinition($graphKey, $version->nodes, $version->edges);

        // Deliberately not `DB::transaction()` — that helper commits on a
        // normal return, and a normal return (a completed/paused/failed
        // graph run) is exactly the expected outcome here, every time.
        // This must always roll back, never commit, so it manages the
        // transaction directly instead.
        DB::beginTransaction();

        try {
            $contentId = $this->testContent->create('[test run] '.$definition->name, $sourceLanguage, $testTranscript);
            $run = AiAnalysisRun::query()->create(['content_id' => $contentId, 'status' => AiAnalysisRun::STATUS_PENDING]);

            $state = new GraphState(['ai_analysis_run_id' => $run->id, 'test_mode' => true]);
            $result = $this->runner->run($definition, $state);

            return new GraphTestRunResult(
                status: $result->status,
                currentNode: $result->currentStepKey,
                pauseReason: $result->state->pauseReason(),
                failureReason: $result->failureReason,
                lexemeCandidates: $run->lexemeCandidates()->get()->toArray(),
                grammarCandidates: $run->grammarCandidates()->get()->toArray(),
            );
        } finally {
            DB::rollBack();
        }
    }
}
