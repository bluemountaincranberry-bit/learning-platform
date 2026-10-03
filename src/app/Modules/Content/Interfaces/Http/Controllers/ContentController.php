<?php

namespace App\Modules\Content\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ContentIndexRequest;
use App\Http\Requests\Api\ImportYoutubeTranscriptRequest;
use App\Http\Requests\Api\SubmitYoutubeRequest;
use App\Http\Resources\ContentResource;
use App\Modules\Content\Application\Contracts\ContentServiceInterface;
use App\Modules\Content\Application\Data\ContentLearnerContext;
use App\Modules\Content\Domain\Models\Content;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ContentController extends Controller
{
    public function __construct(
        private ContentServiceInterface $contentService
    ) {}

    public function categories(): JsonResponse
    {
        return response()->json([
            'categories' => Content::TYPES,
        ]);
    }

    public function index(ContentIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['per_page'] = $request->getPerPage();
        // These routes are public (no `auth:sanctum` middleware), so the
        // default `web` session guard never resolves a user for a token-based
        // SPA request — explicitly check the `sanctum` guard to opportunistically
        // attach progress when the caller happens to be authenticated.
        $authUser = $request->user('sanctum');

        // 'scope=mine' is resolved here (not in the repository) against
        // whichever user the request actually authenticates as — a guest
        // requesting 'mine' has no submissions to scope to, so it's simply
        // dropped rather than erroring.
        if (($validated['scope'] ?? null) === 'mine' && $authUser !== null) {
            $validated['created_by'] = $authUser->id;
        }
        unset($validated['scope']);

        $paginator = $this->contentService->getReadyPaginatedWithProgress(
            $authUser === null ? null : $this->learnerContext($authUser),
            $validated
        );

        return ContentResource::collection($paginator)->response();
    }

    public function show(Request $request, Content $content): JsonResponse
    {
        abort_unless(Gate::allows('view', $content), 404);

        // Same public-route guard note as index() above.
        $user = $request->user('sanctum');
        $p = $this->contentService->getProgressForContent(
            $content,
            $user === null ? null : $this->learnerContext($user),
        );
        $content->learned_count = $p['learned_count'];
        $content->in_learning_count = $p['in_learning_count'];
        $content->total_lexemes = $p['total_lexemes'];
        $content->progress_pct = $p['progress_pct'];
        $content->include_source_text = true;

        return response()->json([
            'content' => new ContentResource($content),
        ]);
    }

    public function lexemes(Request $request, Content $content): JsonResponse
    {
        abort_unless(Gate::allows('view', $content), 404);

        $user = $request->user();
        $items = $this->contentService->getLexemesWithLearnedFlags(
            $content,
            $user === null ? null : $this->learnerContext($user),
        );

        return response()->json(['lexemes' => $items]);
    }

    public function submitYoutube(SubmitYoutubeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $content = $this->contentService->submitYoutube($data, $request->user()->id);

        return response()->json([
            'message' => 'Submission accepted',
            'content' => new ContentResource($content->fresh()),
        ], 201);
    }

    public function importYoutubeTranscript(ImportYoutubeTranscriptRequest $request): JsonResponse
    {
        $content = $this->contentService->importYoutubeTranscript($request->validated(), $request->user()->id);

        return response()->json([
            'message' => 'Transcript imported',
            'content' => new ContentResource($content->fresh()),
        ], 201);
    }

    public function mySubmissions(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $items = $this->contentService->getMySubmissions($user->id);

        return response()->json([
            'data' => ContentResource::collection($items),
        ]);
    }

    private function learnerContext(object $user): ContentLearnerContext
    {
        return new ContentLearnerContext(
            userId: (int) $user->id,
            translationLanguage: $user->translation_language,
            learningGoal: $user->learning_goal,
            currentLevel: $user->current_level,
        );
    }
}
