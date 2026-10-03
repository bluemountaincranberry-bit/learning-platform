<?php

namespace App\Modules\Content\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\AdminGrammarTopicIndexRequest;
use App\Http\Requests\Api\Admin\AdminGrammarTopicStoreRequest;
use App\Http\Requests\Api\Admin\AdminGrammarTopicUpdateRequest;
use App\Modules\Content\Actions\CreateGrammarTopic;
use App\Modules\Content\Actions\DeleteGrammarTopic;
use App\Modules\Content\Actions\UpdateGrammarTopic;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Content\Interfaces\Http\Resources\AdminGrammarTopicResource;
use App\Modules\Content\Queries\GrammarTopicCatalogQuery;
use Illuminate\Http\JsonResponse;

class AdminGrammarTopicController extends Controller
{
    public function __construct(
        private GrammarTopicCatalogQuery $topicCatalogQuery,
        private CreateGrammarTopic $createGrammarTopic,
        private UpdateGrammarTopic $updateGrammarTopic,
        private DeleteGrammarTopic $deleteGrammarTopic,
    ) {}

    public function index(AdminGrammarTopicIndexRequest $request): JsonResponse
    {
        return AdminGrammarTopicResource::collection(
            $this->topicCatalogQuery->paginate($request->validated())
        )->response();
    }

    public function store(AdminGrammarTopicStoreRequest $request): JsonResponse
    {
        $topic = $this->createGrammarTopic->execute($request->validated());

        return response()->json([
            'topic' => new AdminGrammarTopicResource($topic),
        ], 201);
    }

    public function show(GrammarTopic $topic): JsonResponse
    {
        return response()->json([
            'topic' => new AdminGrammarTopicResource($this->topicCatalogQuery->detail($topic)),
        ]);
    }

    public function update(AdminGrammarTopicUpdateRequest $request, GrammarTopic $topic): JsonResponse
    {
        return response()->json([
            'topic' => new AdminGrammarTopicResource(
                $this->updateGrammarTopic->execute($topic, $request->validated())
            ),
        ]);
    }

    public function destroy(GrammarTopic $topic): JsonResponse
    {
        $this->deleteGrammarTopic->execute($topic);

        return response()->json([], 204);
    }
}
