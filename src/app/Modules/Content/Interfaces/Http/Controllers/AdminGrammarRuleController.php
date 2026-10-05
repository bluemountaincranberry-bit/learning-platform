<?php

namespace App\Modules\Content\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\AdminGrammarRuleIndexRequest;
use App\Http\Requests\Api\Admin\AdminGrammarRuleStoreRequest;
use App\Http\Requests\Api\Admin\AdminGrammarRuleUpdateRequest;
use App\Modules\Content\Actions\CreateGrammarRule;
use App\Modules\Content\Actions\DeleteGrammarRule;
use App\Modules\Content\Actions\UpdateGrammarRule;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Interfaces\Http\Resources\AdminGrammarRuleResource;
use App\Modules\Content\Queries\GrammarRuleCatalogQuery;
use Illuminate\Http\JsonResponse;

class AdminGrammarRuleController extends Controller
{
    public function __construct(
        private GrammarRuleCatalogQuery $ruleCatalogQuery,
        private CreateGrammarRule $createGrammarRule,
        private UpdateGrammarRule $updateGrammarRule,
        private DeleteGrammarRule $deleteGrammarRule,
    ) {}

    public function index(AdminGrammarRuleIndexRequest $request): JsonResponse
    {
        return AdminGrammarRuleResource::collection(
            $this->ruleCatalogQuery->paginate($request->validated())
        )->response();
    }

    public function store(AdminGrammarRuleStoreRequest $request): JsonResponse
    {
        $rule = $this->createGrammarRule->execute($request->validated());

        return response()->json([
            'rule' => new AdminGrammarRuleResource($this->ruleCatalogQuery->detail($rule)),
        ], 201);
    }

    public function show(GrammarRule $rule): JsonResponse
    {
        $this->authorizeSharedRule($rule);

        return response()->json([
            'rule' => new AdminGrammarRuleResource(
                $this->ruleCatalogQuery->detail($rule)
            ),
        ]);
    }

    public function update(AdminGrammarRuleUpdateRequest $request, GrammarRule $rule): JsonResponse
    {
        $this->authorizeSharedRule($rule);
        $updatedRule = $this->updateGrammarRule->execute($rule, $request->validated());

        return response()->json([
            'rule' => new AdminGrammarRuleResource($this->ruleCatalogQuery->detail($updatedRule)),
        ]);
    }

    public function destroy(GrammarRule $rule): JsonResponse
    {
        $this->authorizeSharedRule($rule);
        $this->deleteGrammarRule->execute($rule);

        return response()->json([], 204);
    }

    private function authorizeSharedRule(GrammarRule $rule): void
    {
        abort_unless($rule->owner_user_id === null, 404);
    }
}
