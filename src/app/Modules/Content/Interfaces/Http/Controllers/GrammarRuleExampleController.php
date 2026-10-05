<?php

namespace App\Modules\Content\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\GrammarRuleExampleResource;
use App\Modules\Content\Application\Contracts\GrammarRuleExampleGenerationsInterface;
use App\Modules\Content\Application\Contracts\GrammarRuleExampleReaderInterface;
use App\Modules\Content\Application\Data\GrammarRuleExampleGenerationRequest;
use App\Modules\Content\Domain\Models\GrammarRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Rule page examples (VIK-39): the list (with the latest AI batch status,
 * for polling after "More examples"), queuing more AI examples, and
 * hiding a bad example for the current learner.
 */
class GrammarRuleExampleController extends Controller
{
    public function __construct(
        private GrammarRuleExampleReaderInterface $reader,
        private GrammarRuleExampleGenerationsInterface $generations,
    ) {}

    public function index(Request $request, GrammarRule $rule): JsonResponse
    {
        $userId = $request->user('sanctum')?->id;
        Gate::forUser($request->user('sanctum'))->authorize('view', $rule);

        // Public route: the sanctum guard is checked explicitly, as in GrammarRuleController.
        $examples = $rule->status === GrammarRule::STATUS_PERSONAL
            ? $rule->examples()->orderBy('sort_order')->get()
            : $this->reader->forLearner($rule->id, $userId);

        return response()->json([
            'examples' => GrammarRuleExampleResource::collection($examples),
            'generation' => ['status' => $this->generations->latestStatus($rule->id)],
        ]);
    }

    public function generate(Request $request, GrammarRule $rule): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $rule);

        $user = $request->user();
        $result = $this->generations->request(
            $rule->id,
            $user->id,
            (int) config('ai.examples.more_count', 4),
            $user->translation_language ?: null,
        );

        $status = match ($result->status) {
            GrammarRuleExampleGenerationRequest::QUEUED => 202,
            GrammarRuleExampleGenerationRequest::LIMITED => 429,
            GrammarRuleExampleGenerationRequest::UNAVAILABLE => 503,
            default => 200,
        };

        return response()->json(['status' => $result->status], $status);
    }

    public function hide(Request $request, GrammarRule $rule, int $example): Response
    {
        Gate::forUser($request->user())->authorize('view', $rule);
        abort_unless($this->reader->hide($rule->id, $example, $request->user()->id), 404);

        return response()->noContent();
    }

}
