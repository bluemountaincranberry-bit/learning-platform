<?php

namespace App\Modules\Content\Interfaces\Http\Controllers;

use App\Exceptions\AiClientException;
use App\Http\Controllers\Controller;
use App\Contracts\Ai\ContentExamGenerationCapability;
use App\Modules\Content\Actions\RecordContentExamAttempt;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Queries\ContentReadinessQuery;
use App\Support\AiConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Ready to watch" quest: two steps derived live from existing progress
 * (words/grammar learned, see ContentReadinessQuery::stepsStatus()) plus
 * a gated exam. Open to any authenticated learner for any content — unlike
 * ContentAiSuggestionsController this isn't owner-restricted, it's the
 * viewer's own readiness to consume this content, not a review action on it.
 */
class ContentReadinessController extends Controller
{
    public function __construct(
        private readonly ContentReadinessQuery $readinessQuery,
        private readonly RecordContentExamAttempt $recordContentExamAttempt,
        private readonly ContentExamGenerationCapability $sentencePracticeService,
    ) {}

    public function show(Request $request, Content $content): JsonResponse
    {
        return response()->json($this->readinessQuery->readiness($request->user()->id, $content));
    }

    /**
     * Generates the exam cards. Does not persist anything itself — the
     * learner answers each card via the existing /api/practice/sentences/check
     * endpoint (already server-graded there), then submits the accumulated
     * results to complete() below, which is the only step that writes a
     * content_exam_attempts row.
     */
    public function examStart(Request $request, Content $content): JsonResponse
    {
        if (! AiConfig::isEnabled()) {
            return response()->json(['message' => 'AI feature is disabled.'], 503);
        }

        $steps = $this->readinessQuery->stepsStatus($request->user()->id, $content);
        if (! $steps['exam_unlocked']) {
            return response()->json(['message' => 'Finish all words and grammar for this content first.'], 409);
        }

        try {
            $cards = $this->sentencePracticeService->generateContentExam($request->user()->id, $content->id, (int) config('ai.exam.card_count', 8));
        } catch (AiClientException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json(['cards' => $cards]);
    }

    public function examComplete(Request $request, Content $content): JsonResponse
    {
        // Re-checked, not just trusted from examStart's earlier response —
        // guards against completing an exam for content whose steps were
        // un-marked in between (or a direct call that skipped start()).
        $steps = $this->readinessQuery->stepsStatus($request->user()->id, $content);
        if (! $steps['exam_unlocked']) {
            return response()->json(['message' => 'Finish all words and grammar for this content first.'], 409);
        }

        $validated = $request->validate([
            'results' => ['required', 'array', 'min:1'],
            'results.*.prompt_sentence' => ['required', 'string'],
            'results.*.prompt_language' => ['required', 'string'],
            'results.*.answer_language' => ['required', 'string'],
            'results.*.answer' => ['required', 'string'],
            'results.*.correct' => ['required', 'boolean'],
            'results.*.model_answer' => ['sometimes', 'string'],
            'results.*.hint_words' => ['sometimes', 'array'],
        ]);

        $attempt = $this->recordContentExamAttempt->execute($request->user()->id, $content, $validated['results']);

        return response()->json($attempt, 201);
    }
}
