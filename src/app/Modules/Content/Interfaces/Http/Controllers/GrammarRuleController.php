<?php

namespace App\Modules\Content\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GrammarRuleIndexRequest;
use App\Http\Resources\GrammarRuleResource;
use App\Modules\Content\Application\Contracts\GrammarCatalogServiceInterface;
use App\Modules\Content\Application\Contracts\GrammarProgressServiceInterface;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\GrammarRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Public/learner-facing grammar catalog — distinct from the admin-only
 * AdminGrammarRuleController under /admin/grammar. Always forces
 * status=published regardless of what's requested, so draft/review/archived
 * rules never leak to learners (same principle as Content's ready-only
 * public visibility).
 */
class GrammarRuleController extends Controller
{
    public function __construct(
        private GrammarCatalogServiceInterface $grammarCatalogService,
        private GrammarProgressServiceInterface $grammarProgressService
    ) {}

    public function index(GrammarRuleIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $filters['status'] = GrammarRule::STATUS_PUBLISHED;

        $paginator = $this->grammarCatalogService->paginateRules($filters);

        $this->annotateWithProgress($request, collect($paginator->items()));

        return GrammarRuleResource::collection($paginator)->response();
    }

    public function show(Request $request, GrammarRule $rule): JsonResponse
    {
        abort_unless($rule->status === GrammarRule::STATUS_PUBLISHED, 404);

        $rule = $this->grammarCatalogService->getRule($rule);
        $this->annotateWithProgress($request, collect([$rule]));

        return response()->json([
            'rule' => new GrammarRuleResource($rule),
        ]);
    }

    public function forContent(Request $request, Content $content): JsonResponse
    {
        abort_unless(Gate::allows('view', $content), 404);

        $rules = $content->grammarRules()
            ->where('grammar_rules.status', GrammarRule::STATUS_PUBLISHED)
            ->with('examples')
            ->get();

        $this->annotateWithProgress($request, $rules);

        return response()->json([
            'rules' => GrammarRuleResource::collection($rules),
        ]);
    }

    public function startLearning(Request $request, GrammarRule $rule): JsonResponse
    {
        abort_unless($rule->status === GrammarRule::STATUS_PUBLISHED, 404);

        $this->grammarProgressService->startLearning($rule, $request->user()->id);

        return response()->json(['ok' => true]);
    }

    public function markLearned(Request $request, GrammarRule $rule): JsonResponse
    {
        abort_unless($rule->status === GrammarRule::STATUS_PUBLISHED, 404);

        $this->grammarProgressService->markLearned($rule, $request->user()->id);

        return response()->json(['ok' => true]);
    }

    public function unmarkLearned(Request $request, GrammarRule $rule): JsonResponse
    {
        abort_unless($rule->status === GrammarRule::STATUS_PUBLISHED, 404);

        $this->grammarProgressService->unmarkLearned($rule, $request->user()->id);

        return response()->json(['ok' => true]);
    }

    /**
     * Sets the learner's own self-rated confidence (confidence_manual) —
     * independent of confidence_calculated, which only GrammarConfidenceService
     * writes. See the migration that added both columns for why they're kept
     * separate rather than blended into one number.
     */
    public function setConfidence(Request $request, GrammarRule $rule): JsonResponse
    {
        abort_unless($rule->status === GrammarRule::STATUS_PUBLISHED, 404);

        $validated = $request->validate([
            'confidence' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $this->grammarProgressService->setManualConfidence($rule, $request->user()->id, $validated['confidence'] ?? null);

        return response()->json(['ok' => true]);
    }

    /**
     * Sets in_my_list/learned as dynamic (uncast) attributes on each rule, the
     * same technique ContentService::getReadyPaginatedWithProgress uses for
     * learned_count/total_lexemes — GrammarRuleResource reads them back
     * conditionally via $this->when(...).
     *
     * @param  Collection<int, GrammarRule>  $rules
     */
    private function annotateWithProgress(Request $request, Collection $rules): void
    {
        // index/show/forContent are public routes (no `auth:sanctum`), so the
        // default `web` session guard never resolves a user for a token-based
        // SPA request — same fix as ContentController::index()/show() for the
        // identical situation: explicitly check the `sanctum` guard to
        // opportunistically attach progress when the caller happens to be
        // authenticated.
        $user = $request->user('sanctum');

        if ($user === null) {
            return;
        }

        $flags = $this->grammarProgressService->getFlagsForUser($user->id);

        foreach ($rules as $rule) {
            $rule->in_my_list = $flags['in_my_list']->has($rule->id);
            $rule->learned = $flags['learned']->has($rule->id);
            $rule->confidence_manual = $flags['confidence_manual']->get($rule->id);
            $rule->confidence_calculated = $flags['confidence_calculated']->get($rule->id);
        }
    }
}
