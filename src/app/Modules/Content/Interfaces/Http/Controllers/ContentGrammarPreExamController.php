<?php

namespace App\Modules\Content\Interfaces\Http\Controllers;

use App\Exceptions\AiClientException;
use App\Http\Controllers\Controller;
use App\Contracts\Ai\ContentExamGenerationCapability;
use App\Modules\Content\Application\GrammarConfidenceService;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\GrammarExamAttempt;
use App\Support\AiConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Grammar warm-up: unlike ContentReadinessController's "Ready to watch"
 * exam (gated behind every word/rule of the content already being marked
 * learned, one aggregate score for the whole session), this is open at any
 * time and scoped to whichever of the content's own grammar rules the
 * learner picks — the diagnostic-before-you-start flow the "pre" type is
 * for, plus its mirror image ("post") run again after finishing the
 * content. Each selected rule gets its own GrammarExamAttempt row (not one
 * row for the whole session, unlike ContentExamAttempt) so
 * GrammarConfidenceService can recompute confidence per rule, not just an
 * overall pass/fail.
 */
class ContentGrammarPreExamController extends Controller
{
    public function __construct(
        private readonly ContentExamGenerationCapability $sentencePracticeService,
        private readonly GrammarConfidenceService $confidenceService,
    ) {}

    /**
     * Generates the warm-up cards. Does not persist anything itself — the
     * learner answers each card via the existing /api/practice/sentences/check
     * endpoint (already server-graded there), then submits the accumulated
     * results to complete() below, which is the only step that writes
     * grammar_exam_attempts rows.
     */
    public function start(Request $request, Content $content): JsonResponse
    {
        if (! AiConfig::isEnabled()) {
            return response()->json(['message' => 'AI feature is disabled.'], 503);
        }

        $validated = $request->validate([
            'grammar_rule_ids' => ['required', 'array', 'min:1'],
            'grammar_rule_ids.*' => ['integer'],
            'count' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        $rules = $content->grammarRules()->whereIn('grammar_rules.id', $validated['grammar_rule_ids'])->get();

        if ($rules->isEmpty()) {
            return response()->json(['message' => 'None of the selected topics are linked to this content.'], 422);
        }

        try {
            $count = (int) ($validated['count'] ?? config('ai.pre_exam.card_count', 6));
            $cards = $this->sentencePracticeService->generateGrammarWarmup($request->user()->id, $content->id, $rules->pluck('id')->all(), $count);
        } catch (AiClientException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json(['cards' => $cards]);
    }

    /**
     * Groups the submitted results by grammar_rule_id, writes one
     * GrammarExamAttempt per rule, and recalculates confidence_calculated
     * for each affected rule — so the response can show the learner
     * "before -> after" per topic.
     */
    public function complete(Request $request, Content $content): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:'.implode(',', GrammarExamAttempt::TYPES)],
            'results' => ['required', 'array', 'min:1'],
            'results.*.grammar_rule_id' => ['required', 'integer'],
            'results.*.prompt_sentence' => ['required', 'string'],
            'results.*.prompt_language' => ['required', 'string'],
            'results.*.answer_language' => ['required', 'string'],
            'results.*.answer' => ['required', 'string'],
            'results.*.correct' => ['required', 'boolean'],
            'results.*.model_answer' => ['sometimes', 'string'],
            'results.*.hint_words' => ['sometimes', 'array'],
        ]);

        $submittedRuleIds = collect($validated['results'])->pluck('grammar_rule_id')->unique();
        // Re-checked against the content's own links, not just trusted from
        // the client — guards against a tampered grammar_rule_id crediting a
        // rule this content never covered.
        $ownedRuleIds = $content->grammarRules()->whereIn('grammar_rules.id', $submittedRuleIds)->pluck('grammar_rules.id');

        $byRule = collect($validated['results'])
            ->filter(fn (array $r) => $ownedRuleIds->contains($r['grammar_rule_id']))
            ->groupBy('grammar_rule_id');

        if ($byRule->isEmpty()) {
            return response()->json(['message' => 'No valid results to record.'], 422);
        }

        $attempts = [];
        foreach ($byRule as $ruleId => $items) {
            $total = $items->count();
            $correct = $items->where('correct', true)->count();
            $scorePct = round(($correct / $total) * 100, 2);

            $attempt = GrammarExamAttempt::query()->create([
                'user_id' => $request->user()->id,
                'grammar_rule_id' => $ruleId,
                'content_id' => $content->id,
                'type' => $validated['type'],
                'total_cards' => $total,
                'correct_count' => $correct,
                'score_pct' => $scorePct,
                'items' => $items->values()->all(),
                'completed_at' => now(),
            ]);

            $confidenceCalculated = $this->confidenceService->recalculate($request->user()->id, $attempt->grammarRule);

            $attempts[] = [
                'grammar_rule_id' => (int) $ruleId,
                'attempt' => $attempt,
                'confidence_calculated' => $confidenceCalculated,
            ];
        }

        return response()->json(['attempts' => $attempts], 201);
    }
}
