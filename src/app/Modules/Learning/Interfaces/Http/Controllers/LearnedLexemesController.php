<?php

namespace App\Modules\Learning\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LearnedLexemesIndexRequest;
use App\Modules\Learning\Application\LearnedLexemesService;
use App\Modules\Learning\Interfaces\Http\Resources\LearnedLexemeResource;
use Illuminate\Http\JsonResponse;

class LearnedLexemesController extends Controller
{
    public function __construct(
        private LearnedLexemesService $learnedLexemesService
    ) {}

    public function index(LearnedLexemesIndexRequest $request): JsonResponse
    {
        $paginator = $this->learnedLexemesService->getPaginated(
            $request->user()->id,
            $request->user()->translation_language ?? config('ai.analysis.translation_language', 'ru'),
            $request->validated()
        );

        return LearnedLexemeResource::collection($paginator)->response();
    }
}
