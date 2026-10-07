<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\GrammarExercisePoolInterface;
use App\Modules\Content\Application\Data\GrammarPracticeExercise;
use App\Modules\Content\Application\Data\GrammarPracticeRule;
use App\Modules\Content\Domain\Models\GrammarExerciseReport;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarRuleExercise;
use Illuminate\Database\Eloquent\Builder;

final class GrammarExercisePool implements GrammarExercisePoolInterface
{
    public function __construct(private readonly GrammarAnswerChecker $checker) {}

    public function practiceRule(int $ruleId, int $userId): ?GrammarPracticeRule
    {
        $ruleQuery = GrammarRule::query()->whereKey($ruleId);
        $this->scopeRulesVisibleTo($ruleQuery, $userId);
        $rule = $ruleQuery
            ->first(['id', 'title']);

        return $rule === null ? null : new GrammarPracticeRule($rule->id, $rule->title);
    }

    public function available(int $ruleId, int $userId): array
    {
        return $this->visibleTo($userId)
            ->where('grammar_rule_id', $ruleId)
            ->orderBy('id')
            ->get()
            ->map(fn (GrammarRuleExercise $exercise) => $this->toData($exercise))
            ->all();
    }

    public function find(int $exerciseId, int $userId): ?GrammarPracticeExercise
    {
        $exercise = $this->visibleTo($userId)->whereKey($exerciseId)->first();

        return $exercise === null ? null : $this->toData($exercise);
    }

    public function isCorrect(GrammarPracticeExercise $exercise, string|int $given): bool
    {
        if ($exercise->type === GrammarRuleExercise::TYPE_MULTIPLE_CHOICE) {
            return is_numeric($given) && (int) $given === $exercise->answerIndex;
        }

        return $this->checker->typedMatches((string) $given, $exercise->answer, $exercise->acceptedAnswers);
    }

    public function report(int $userId, int $exerciseId, ?string $reason): void
    {
        GrammarExerciseReport::query()->firstOrCreate(
            ['user_id' => $userId, 'grammar_rule_exercise_id' => $exerciseId],
            ['reason' => $reason],
        );
    }

    public function reportedBy(int $userId, int $ruleId): array
    {
        return GrammarExerciseReport::query()
            ->where('user_id', $userId)
            ->whereHas('exercise', fn (Builder $q) => $q->where('grammar_rule_id', $ruleId))
            ->pluck('grammar_rule_exercise_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return Builder<GrammarRuleExercise> */
    private function visibleTo(int $userId): Builder
    {
        return GrammarRuleExercise::query()
            ->whereHas('grammarRule', fn (Builder $query) => $this->scopeRulesVisibleTo($query, $userId))
            ->where('status', '!=', GrammarRuleExercise::STATUS_ARCHIVED)
            ->where(fn (Builder $q) => $q
                ->where('status', GrammarRuleExercise::STATUS_PUBLISHED)
                ->orWhere('origin', GrammarRuleExercise::ORIGIN_AI))
            ->whereDoesntHave('reports', fn (Builder $q) => $q->where('user_id', $userId))
            ->has('reports', '<', GrammarRuleExercise::HIDE_AFTER_REPORTS);
    }

    /** @param Builder<GrammarRule> $query */
    private function scopeRulesVisibleTo(Builder $query, int $userId): void
    {
        $query->where(function (Builder $visibility) use ($userId): void {
            $visibility->where(fn (Builder $shared) => $shared
                ->where('status', GrammarRule::STATUS_PUBLISHED)
                ->whereNull('owner_user_id'))
                ->orWhere(fn (Builder $personal) => $personal
                    ->where('status', GrammarRule::STATUS_PERSONAL)
                    ->where('owner_user_id', $userId));
        });
    }

    private function toData(GrammarRuleExercise $exercise): GrammarPracticeExercise
    {
        $options = is_array($exercise->options) ? array_values($exercise->options) : null;
        $answer = $exercise->type === GrammarRuleExercise::TYPE_MULTIPLE_CHOICE
            ? (string) ($options[$exercise->answer_index] ?? '')
            : (string) $exercise->answer;

        return new GrammarPracticeExercise(
            id: $exercise->id,
            ruleId: $exercise->grammar_rule_id,
            type: $exercise->type,
            level: GrammarRuleExercise::levelOf($exercise->type),
            instruction: $exercise->instruction,
            prompt: $exercise->prompt,
            options: $options,
            tiles: is_array($exercise->tiles) ? array_values($exercise->tiles) : null,
            answer: $answer,
            answerIndex: $exercise->answer_index,
            acceptedAnswers: array_values(array_filter((array) $exercise->accepted_answers, 'is_string')),
            hint: $exercise->hint,
            explanation: $exercise->explanation,
            origin: $exercise->origin ?? GrammarRuleExercise::ORIGIN_ADMIN,
        );
    }
}
