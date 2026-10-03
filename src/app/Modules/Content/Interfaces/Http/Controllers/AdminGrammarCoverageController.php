<?php

namespace App\Modules\Content\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\AdminGrammarRuleIndexRequest;
use App\Modules\Content\Application\Contracts\GrammarCatalogServiceInterface;
use Illuminate\Http\JsonResponse;

class AdminGrammarCoverageController extends Controller
{
    public function __construct(
        private GrammarCatalogServiceInterface $grammarCatalogService
    ) {}

    public function __invoke(AdminGrammarRuleIndexRequest $request): JsonResponse
    {
        return response()->json([
            'coverage' => $this->grammarCatalogService->getCoverageSummary($request->validated()),
        ]);
    }
}
