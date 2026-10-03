<?php

namespace App\Modules\Content\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AcceptAiSuggestionsRequest;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentGrammarCandidate;
use App\Modules\Content\Domain\Models\ContentLexemeCandidate;
use App\Modules\Content\Application\Contracts\CandidateApplicationInterface;
use App\Modules\Content\Application\AiAnalysisAutoDispatchService;
use Illuminate\Http\JsonResponse;

/**
 * EPIC 9 (tasks 9.2/9.3, endpoints kept after 9.8): user-facing
 * (non-Filament) view of AI-extracted candidates. Authorization is
 * `content.created_by === auth()->id()` via
 * ContentPolicy::reviewAiSuggestions() — a different gate from Filament's
 * admin-only ApplyAiCandidatesAction, but the same underlying accept-then-
 * apply sequence (the shared candidate application contract) as the RelationManagers'
 * acceptSelected bulk action + the "Apply approved AI candidates" action.
 *
 * Task 9.8 (explicit user decision, supersedes 9.4's original framing):
 * candidates now auto-apply with no human confirmation
 * (AiCandidateAutoApplyService, run from RunAiContentAnalysisJob right
 * after analysis). In normal operation nothing is ever `pending` by the
 * time a user could load this endpoint — `lexeme_candidates`/
 * `grammar_candidates` below stay as a harmless legacy/fallback surface
 * (e.g. a run from before 9.8 shipped, or a future manual re-trigger), and
 * `applied_lexeme_count`/`applied_grammar_count` are the new primary
 * signal: what AI already added, for the SPA's read-only "AI just added..."
 * notice (task 9.4, converted from an accept-gate to a passive summary).
 */
class ContentAiSuggestionsController extends Controller
{
    public function __construct(
        private readonly CandidateApplicationInterface $applyService,
        private readonly AiAnalysisAutoDispatchService $autoDispatchService
    ) {}

    /**
     * Lets the content's own submitter trigger a fresh analysis pass on
     * demand — e.g. after this class's defaults changed (more generous
     * thoroughness/level-scaled extraction) or their profile's target level
     * changed, content submitted earlier stays stuck with whatever a past
     * run produced otherwise. Reuses AiAnalysisAutoDispatchService's own
     * guards (no double-run, same daily quota for user-submitted content)
     * rather than re-implementing them here.
     */
    public function reanalyze(Content $content): JsonResponse
    {
        $this->authorize('reviewAiSuggestions', $content);

        $result = $this->autoDispatchService->dispatchFor($content);

        return match ($result) {
            AiAnalysisAutoDispatchService::RESULT_DISPATCHED => response()->json(['status' => 'dispatched'], 202),
            AiAnalysisAutoDispatchService::RESULT_ALREADY_RUNNING => response()->json(['message' => 'An analysis is already in progress for this content.'], 409),
            AiAnalysisAutoDispatchService::RESULT_QUOTA_EXCEEDED => response()->json(['message' => "You've reached today's AI analysis limit — try again tomorrow."], 429),
            default => response()->json(['message' => 'AI analysis is not available for this content right now.'], 503),
        };
    }

    public function index(Content $content): JsonResponse
    {
        $this->authorize('reviewAiSuggestions', $content);

        $run = $content->latestAnalysisRun;

        if (! $run) {
            return response()->json([
                'run_id' => null,
                'lexeme_candidates' => [],
                'grammar_candidates' => [],
                'applied_lexeme_count' => 0,
                'applied_grammar_count' => 0,
            ]);
        }

        $lexemeCandidates = $run->lexemeCandidates()
            ->where('status', ContentLexemeCandidate::STATUS_PENDING)
            ->orderBy('id')
            ->get()
            ->map(fn (ContentLexemeCandidate $c): array => [
                'id' => $c->id,
                'text' => $c->text,
                'type' => $c->type,
                'level' => $c->level,
                'translation' => $c->translation,
                'example' => $c->example,
                'example_translation' => $c->example_translation,
                // Task 9.9: full multi-example set, when present (candidates
                // created before 9.9 shipped only ever have the two columns
                // above).
                'examples' => $c->examples,
                'note' => $c->note,
                'confidence' => $c->confidence,
            ])->values();

        $grammarCandidates = $run->grammarCandidates()
            ->where('status', ContentGrammarCandidate::STATUS_PENDING)
            ->orderBy('id')
            ->get()
            ->map(fn (ContentGrammarCandidate $c): array => [
                'id' => $c->id,
                'title' => $c->title,
                'summary' => $c->summary,
                'example' => $c->example,
                'example_translation' => $c->example_translation,
                'note' => $c->note,
                'confidence' => $c->confidence,
            ])->values();

        return response()->json([
            'run_id' => $run->id,
            'lexeme_candidates' => $lexemeCandidates,
            'grammar_candidates' => $grammarCandidates,
            'applied_lexeme_count' => $run->lexemeCandidates()->where('status', ContentLexemeCandidate::STATUS_APPLIED)->count(),
            'applied_grammar_count' => $run->grammarCandidates()->where('status', ContentGrammarCandidate::STATUS_APPLIED)->count(),
        ]);
    }

    /**
     * Accepts the given (or all) pending candidates on the latest run, then
     * applies the run exactly like ApplyAiCandidatesAction does — one
     * request, matching task 9.4's "pre-selected, one click" UX rather than
     * a separate accept step + separate apply step.
     */
    public function accept(AcceptAiSuggestionsRequest $request, Content $content): JsonResponse
    {
        $this->authorize('reviewAiSuggestions', $content);

        $run = $content->latestAnalysisRun;

        if (! $run) {
            return response()->json(['message' => 'No analysis run for this content.'], 404);
        }

        $data = $request->validated();
        $acceptAll = (bool) ($data['accept_all'] ?? false);
        $lexemeIds = $data['lexeme_candidate_ids'] ?? [];
        $grammarIds = $data['grammar_candidate_ids'] ?? [];

        if (! $acceptAll && $lexemeIds === [] && $grammarIds === []) {
            return response()->json(['message' => 'Nothing to accept.'], 422);
        }

        $lexemeQuery = $run->lexemeCandidates()->where('status', ContentLexemeCandidate::STATUS_PENDING);
        if (! $acceptAll) {
            $lexemeQuery->whereIn('id', $lexemeIds);
        }
        $lexemeQuery->update(['status' => ContentLexemeCandidate::STATUS_ACCEPTED]);

        $grammarQuery = $run->grammarCandidates()->where('status', ContentGrammarCandidate::STATUS_PENDING);
        if (! $acceptAll) {
            $grammarQuery->whereIn('id', $grammarIds);
        }
        $grammarQuery->update(['status' => ContentGrammarCandidate::STATUS_ACCEPTED]);

        $result = $this->applyService->apply($run);

        return response()->json(['applied' => $result]);
    }
}
