<?php

namespace App\Modules\Learning\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GrammarRulesMineIndexRequest;
use App\Modules\Content\Application\Contracts\GrammarProgressServiceInterface;
use App\Modules\Learning\Interfaces\Http\Resources\LearnedGrammarRuleResource;
use Illuminate\Http\JsonResponse;

class LearnedGrammarRulesController extends Controller
{
    public function __construct(
        private GrammarProgressServiceInterface $grammarProgressService
    ) {}

    public function index(GrammarRulesMineIndexRequest $request): JsonResponse
    {
        $paginator = $this->grammarProgressService->getPaginated(
            $request->user()->id,
            $request->validated()
        );

        return LearnedGrammarRuleResource::collection($paginator)->response();
    }
}
