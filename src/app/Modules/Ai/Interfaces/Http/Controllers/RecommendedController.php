<?php

namespace App\Modules\Ai\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RecommendedContentsRequest;
use App\Http\Requests\Api\RecommendedLexemesRequest;
use App\Http\Resources\ContentResource;
use App\Modules\Ai\Application\Data\RecommendationLearner;
use App\Modules\Ai\Application\RecommendationService;
use Illuminate\Http\JsonResponse;

class RecommendedController extends Controller
{
    public function __construct(
        private RecommendationService $recommendationService
    ) {}

    public function contents(RecommendedContentsRequest $request): JsonResponse
    {
        $contents = $this->recommendationService->getRecommendedContents(
            RecommendationLearner::fromUser($request->user()),
            $request->getLimit()
        );

        return response()->json([
            'data' => ContentResource::collection($contents),
        ]);
    }

    public function lexemes(RecommendedLexemesRequest $request): JsonResponse
    {
        $lexemes = $this->recommendationService->getRecommendedLexemes(
            RecommendationLearner::fromUser($request->user()),
            $request->getLimit()
        );

        $data = $lexemes->map(fn ($lexeme) => [
            'id' => $lexeme->id,
            'text' => $lexeme->text,
            'content_id' => $lexeme->content_id,
            'score' => (float) ($lexeme->recommendation_score ?? 0),
            'reasons' => $lexeme->recommendation_reasons ?? [],
        ]);

        return response()->json([
            'data' => $data,
        ]);
    }
}
