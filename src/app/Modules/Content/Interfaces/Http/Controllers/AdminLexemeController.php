<?php

namespace App\Modules\Content\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\AdminLexemeIndexRequest;
use App\Http\Requests\Api\Admin\AdminLexemeStoreRequest;
use App\Http\Requests\Api\Admin\AdminLexemeUpdateRequest;
use App\Modules\Content\Actions\CreateLexeme;
use App\Modules\Content\Actions\DeleteLexeme;
use App\Modules\Content\Actions\UpdateLexeme;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Content\Interfaces\Http\Resources\AdminLexemeResource;
use App\Modules\Content\Queries\LexemeCatalogQuery;
use Illuminate\Http\JsonResponse;

class AdminLexemeController extends Controller
{
    public function __construct(
        private LexemeCatalogQuery $lexemeCatalogQuery,
        private CreateLexeme $createLexeme,
        private UpdateLexeme $updateLexeme,
        private DeleteLexeme $deleteLexeme,
    ) {}

    public function index(AdminLexemeIndexRequest $request): JsonResponse
    {
        return AdminLexemeResource::collection(
            $this->lexemeCatalogQuery->paginate($request->validated())
        )->response();
    }

    public function store(AdminLexemeStoreRequest $request): JsonResponse
    {
        return response()->json([
            'lexeme' => new AdminLexemeResource(
                $this->lexemeCatalogQuery->detail($this->createLexeme->execute($request->validated()))
            ),
        ], 201);
    }

    public function show(Lexeme $lexeme): JsonResponse
    {
        $this->authorize('manageCatalog', $lexeme);
        return response()->json([
            'lexeme' => new AdminLexemeResource(
                $this->lexemeCatalogQuery->detail($lexeme)
            ),
        ]);
    }

    public function update(AdminLexemeUpdateRequest $request, Lexeme $lexeme): JsonResponse
    {
        $this->authorize('manageCatalog', $lexeme);
        return response()->json([
            'lexeme' => new AdminLexemeResource(
                $this->lexemeCatalogQuery->detail($this->updateLexeme->execute($lexeme, $request->validated()))
            ),
        ]);
    }

    public function destroy(Lexeme $lexeme): JsonResponse
    {
        $this->authorize('manageCatalog', $lexeme);
        $this->deleteLexeme->execute($lexeme);

        return response()->json([], 204);
    }
}
