<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\GrammarExerciseDraftsInterface;
use App\Modules\Content\Application\Data\GrammarExerciseSource;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarRuleExercise;

final class GrammarExerciseDrafts implements GrammarExerciseDraftsInterface
{
    public function source(int $ruleId): GrammarExerciseSource
    {
        $rule = GrammarRule::query()->with('topic')->findOrFail($ruleId);

        return new GrammarExerciseSource(
            ruleId: $rule->id,
            title: $rule->title,
            topicName: $rule->topic?->name ?? '',
            language: $rule->language ?? 'en',
            summary: $rule->summary ?? '',
            body: $rule->body ?? '',
            examples: $rule->examples()->limit(5)->pluck('example')->filter()->implode(' | '),
        );
    }

    public function createDrafts(int $ruleId, array $items): int
    {
        $rule = GrammarRule::query()->findOrFail($ruleId);
        $created = 0;

        foreach ($items as $item) {
            $attributes = $this->validAttributes($item);
            if ($attributes === null) {
                continue;
            }

            $rule->exercises()->create($attributes);
            $created++;
        }

        return $created;
    }

    private function validAttributes(mixed $item): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        $type = is_string($item['type'] ?? null) ? $item['type'] : null;
        $prompt = is_string($item['prompt'] ?? null) ? trim($item['prompt']) : '';

        if (! in_array($type, GrammarRuleExercise::TYPES, true) || $prompt === '') {
            return null;
        }

        $attributes = [
            'type' => $type,
            'prompt' => $prompt,
            'explanation' => is_string($item['explanation'] ?? null) ? $item['explanation'] : null,
            'status' => GrammarRuleExercise::STATUS_DRAFT,
        ];

        if ($type === GrammarRuleExercise::TYPE_CLOZE) {
            $answer = is_string($item['answer'] ?? null) ? trim($item['answer']) : '';

            return $answer === '' ? null : $attributes + ['answer' => $answer];
        }

        $options = is_array($item['options'] ?? null)
            ? array_values(array_filter($item['options'], 'is_string'))
            : [];
        $answerIndex = is_numeric($item['answer_index'] ?? null) ? (int) $item['answer_index'] : null;

        if (count($options) < 2 || $answerIndex === null || ! isset($options[$answerIndex])) {
            return null;
        }

        return $attributes + ['options' => $options, 'answer_index' => $answerIndex];
    }
}
