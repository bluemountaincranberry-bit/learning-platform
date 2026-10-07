<?php

namespace App\Modules\Learning\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\GrammarPracticePayloadResource;
use App\Modules\Content\Application\Data\GrammarPracticeRule;
use App\Modules\Learning\Application\GrammarPractice\GrammarPracticeLevel;
use App\Modules\Learning\Application\GrammarPractice\GrammarPracticeService;
use App\Modules\Learning\Interfaces\Http\Requests\CheckGrammarPracticeAnswerRequest;
use App\Modules\Learning\Interfaces\Http\Requests\CompleteGrammarPracticeRoundRequest;
use App\Modules\Learning\Interfaces\Http\Requests\ReportGrammarPracticeExerciseRequest;
use App\Modules\Learning\Interfaces\Http\Requests\StartGrammarPracticeRoundRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Grammar practice on a rule (VIK-31): start card, round, answer check,
 * report, finish. See GrammarPracticeService.
 */
class GrammarPracticeController extends Controller
{
    public function __construct(private readonly GrammarPracticeService $practice) {}

    public function show(Request $request, int $rule): JsonResponse
    {
        return (new GrammarPracticePayloadResource($this->practice->overview($request->user()->id, $this->rule($request, $rule))))->response();
    }

    public function startRound(StartGrammarPracticeRoundRequest $request, int $rule): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->practice->startRound(
            $request->user()->id,
            $this->rule($request, $rule),
            GrammarPracticeLevel::from($validated['level']),
            (int) $validated['count'],
            isset($validated['exercise_ids']) ? array_map('intval', $validated['exercise_ids']) : null,
        );

        return (new GrammarPracticePayloadResource($result))->response()->setStatusCode(match ($result['status']) {
            'ready' => 200,
            'preparing' => 202,
            default => 503,
        });
    }

    public function check(CheckGrammarPracticeAnswerRequest $request, int $exercise): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->practice->check(
            $request->user()->id,
            $exercise,
            $validated['given'] ?? null,
            (bool) ($validated['show_answer'] ?? false),
        );

        abort_if($result === null, 404);

        return (new GrammarPracticePayloadResource($result))->response();
    }

    public function report(ReportGrammarPracticeExerciseRequest $request, int $exercise): JsonResponse
    {
        $validated = $request->validated();

        $replacement = $this->practice->reportAndReplace(
            $request->user()->id,
            $exercise,
            GrammarPracticeLevel::from($validated['level']),
            array_map('intval', $validated['round_exercise_ids'] ?? []),
            $validated['reason'] ?? null,
        );

        return (new GrammarPracticePayloadResource(['replacement' => $replacement]))->response();
    }

    public function complete(CompleteGrammarPracticeRoundRequest $request, int $rule): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->practice->complete(
            $request->user()->id,
            $this->rule($request, $rule),
            GrammarPracticeLevel::from($validated['level']),
            $validated['items'],
            $validated['content_id'] ?? null,
            (bool) ($validated['replay'] ?? false),
        );

        return (new GrammarPracticePayloadResource($result))->response()->setStatusCode(201);
    }

    private function rule(Request $request, int $ruleId): GrammarPracticeRule
    {
        $rule = $this->practice->rule($ruleId, $request->user()->id);
        abort_if($rule === null, 404);

        return $rule;
    }
}
