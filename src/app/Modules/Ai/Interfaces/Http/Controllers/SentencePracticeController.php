<?php

namespace App\Modules\Ai\Interfaces\Http\Controllers;

use App\Exceptions\AiClientException;
use App\Http\Controllers\Controller;
use App\Modules\Ai\Application\SentencePracticeService;
use App\Modules\Content\Application\Contracts\ContentViewAuthorizationInterface;
use App\Support\AiConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sentence practice — a bidirectional sentence-translation drill built on
 * whatever the learner recently studied (SentencePracticeService).
 */
class SentencePracticeController extends Controller
{
    public function __construct(
        private readonly SentencePracticeService $service,
        private readonly ContentViewAuthorizationInterface $contentViewAuthorization,
    ) {}

    public function start(Request $request): JsonResponse
    {
        if (! AiConfig::isEnabled()) {
            return response()->json(['message' => 'AI feature is disabled.'], 503);
        }

        $validated = $request->validate([
            'direction' => ['required', 'in:'.SentencePracticeService::DIRECTION_TO_TARGET.','.SentencePracticeService::DIRECTION_TO_NATIVE],
            'count' => ['sometimes', 'integer', 'min:1', 'max:10'],
            // "Reinforce" — content-scoped, ungated, distinct from the
            // gated exam (see ContentReadinessController) which has its own
            // start/complete endpoints and never goes through here.
            'content_id' => ['sometimes', 'integer', 'exists:contents,id'],
        ]);

        try {
            if (isset($validated['content_id'])) {
                $this->contentViewAuthorization->assertCanView($request->user(), $validated['content_id']);
            }

            $result = isset($validated['content_id'])
                ? $this->service->generateForContentId(
                    $request->user(),
                    $validated['content_id'],
                    $validated['direction'],
                    $validated['count'] ?? 5,
                )
                : $this->service->generateBatch($request->user(), $validated['direction'], $validated['count'] ?? 5);
        } catch (AiClientException) {
            return response()->json(['message' => 'AI service unavailable.'], 503);
        }

        return response()->json($result);
    }

    public function check(Request $request): JsonResponse
    {
        if (! AiConfig::isEnabled()) {
            return response()->json(['message' => 'AI feature is disabled.'], 503);
        }

        $validated = $request->validate([
            'prompt_sentence' => ['required', 'string'],
            'prompt_language' => ['required', 'string'],
            'answer_language' => ['required', 'string'],
            'answer' => ['required', 'string'],
            'check_mode' => ['sometimes', 'in:flexible,exact'],
        ]);

        try {
            $result = $this->service->checkAnswer(
                $validated['prompt_sentence'],
                $validated['prompt_language'],
                $validated['answer_language'],
                $validated['answer'],
                $validated['check_mode'] ?? 'flexible',
            );
        } catch (AiClientException) {
            return response()->json(['message' => 'AI service unavailable.'], 503);
        }

        return response()->json($result);
    }
}
