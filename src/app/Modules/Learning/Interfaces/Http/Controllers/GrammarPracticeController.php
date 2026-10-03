<?php

namespace App\Modules\Learning\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Content\Application\Data\GrammarPracticeRule;
use App\Modules\Learning\Application\GrammarPractice\GrammarPracticeLevel;
use App\Modules\Learning\Application\GrammarPractice\GrammarPracticeOutcome;
use App\Modules\Learning\Application\GrammarPractice\GrammarPracticeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Grammar practice on a rule (VIK-31): start card, round, answer check,
 * report, finish. See GrammarPracticeService.
 */
class GrammarPracticeController extends Controller
{
    public function __construct(private readonly GrammarPracticeService $practice) {}

    public function show(Request $request, int $rule): JsonResponse
    {
        return response()->json($this->practice->overview($request->user()->id, $this->rule($rule)));
    }

    public function startRound(Request $request, int $rule): JsonResponse
    {
        $validated = $request->validate([
            'level' => ['required', Rule::enum(GrammarPracticeLevel::class)],
            'count' => ['required', 'integer', Rule::in(GrammarPracticeService::COUNTS)],
            'exercise_ids' => ['sometimes', 'array', 'min:1', 'max:15'],
            'exercise_ids.*' => ['integer'],
        ]);

        $result = $this->practice->startRound(
            $request->user()->id,
            $this->rule($rule),
            GrammarPracticeLevel::from($validated['level']),
            (int) $validated['count'],
            isset($validated['exercise_ids']) ? array_map('intval', $validated['exercise_ids']) : null,
        );

        return response()->json($result, match ($result['status']) {
            'ready' => 200,
            'preparing' => 202,
            default => 503,
        });
    }

    public function check(Request $request, int $exercise): JsonResponse
    {
        $validated = $request->validate([
            'given' => ['nullable', 'string', 'max:500'],
            'show_answer' => ['sometimes', 'boolean'],
        ]);

        $result = $this->practice->check(
            $request->user()->id,
            $exercise,
            $validated['given'] ?? null,
            (bool) ($validated['show_answer'] ?? false),
        );

        abort_if($result === null, 404);

        return response()->json($result);
    }

    public function report(Request $request, int $exercise): JsonResponse
    {
        $validated = $request->validate([
            'level' => ['required', Rule::enum(GrammarPracticeLevel::class)],
            'round_exercise_ids' => ['sometimes', 'array', 'max:30'],
            'round_exercise_ids.*' => ['integer'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $replacement = $this->practice->reportAndReplace(
            $request->user()->id,
            $exercise,
            GrammarPracticeLevel::from($validated['level']),
            array_map('intval', $validated['round_exercise_ids'] ?? []),
            $validated['reason'] ?? null,
        );

        return response()->json(['replacement' => $replacement]);
    }

    public function complete(Request $request, int $rule): JsonResponse
    {
        $validated = $request->validate([
            'level' => ['required', Rule::enum(GrammarPracticeLevel::class)],
            'content_id' => ['nullable', 'integer', 'exists:contents,id'],
            'replay' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.exercise_id' => ['required', 'integer'],
            'items.*.outcome' => ['nullable', Rule::enum(GrammarPracticeOutcome::class)],
            'items.*.ms' => ['nullable', 'integer', 'min:0'],
        ]);

        $result = $this->practice->complete(
            $request->user()->id,
            $this->rule($rule),
            GrammarPracticeLevel::from($validated['level']),
            $validated['items'],
            $validated['content_id'] ?? null,
            (bool) ($validated['replay'] ?? false),
        );

        return response()->json($result, 201);
    }

    private function rule(int $ruleId): GrammarPracticeRule
    {
        $rule = $this->practice->rule($ruleId);
        abort_if($rule === null, 404);

        return $rule;
    }
}
