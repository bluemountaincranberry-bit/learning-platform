<?php

namespace App\Modules\Learning\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TrainingSelectedLexemesRequest;
use App\Modules\Learning\Application\TrainingSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingSessionController extends Controller
{
    public function __construct(
        private TrainingSessionService $trainingSessionService
    ) {}

    public function reviewQueue(Request $request): JsonResponse
    {
        $contentId = $request->filled('content_id') ? (int) $request->query('content_id') : null;
        $user = $request->user();

        return response()->json([
            'items' => $this->trainingSessionService->getReviewQueue(
                (int) $user->id,
                $user->translation_language ?? config('ai.analysis.translation_language', 'ru'),
                $contentId,
            ),
        ]);
    }

    public function selectedLexemes(TrainingSelectedLexemesRequest $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'items' => $this->trainingSessionService->getSelectedLexemes(
                $user->translation_language ?? config('ai.analysis.translation_language', 'ru'),
                $request->lexemeIds(),
            ),
        ]);
    }

    public function selectedCanonicalLexemes(TrainingSelectedLexemesRequest $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'items' => $this->trainingSessionService->getSelectedCanonicalLexemes(
                (int) $user->id,
                $user->translation_language ?? config('ai.analysis.translation_language', 'ru'),
                $request->lexemeIds(),
            ),
        ]);
    }
}
