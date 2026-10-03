<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\GrammarExerciseDraftsInterface;
use App\Modules\Content\Application\Data\GrammarExerciseSource;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarRuleExercise;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

/**
 * Turns AI-generated exercise items into grammar_rule_exercises rows. Every
 * item is validated for its type; broken items are skipped rather than shown
 * to a learner, and a hint that would give the answer away is dropped (the
 * round then falls back to a generic hint).
 */
final class GrammarExerciseDrafts implements GrammarExerciseDraftsInterface
{
    private const MAX_OPTIONS = 6;

    public function __construct(private readonly GrammarAnswerChecker $checker) {}

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

    public function createDrafts(int $ruleId, array $items, string $origin = GrammarRuleExercise::ORIGIN_ADMIN): int
    {
        $rule = GrammarRule::query()->findOrFail($ruleId);
        $origin = in_array($origin, GrammarRuleExercise::ORIGINS, true) ? $origin : GrammarRuleExercise::ORIGIN_ADMIN;
        $seen = array_fill_keys(
            $rule->exercises()->whereNotNull('dedup_key')->pluck('dedup_key')->all(),
            true,
        );
        $created = 0;
        $invalid = 0;
        $duplicates = 0;

        foreach ($items as $item) {
            $attributes = $this->validAttributes($item);
            if ($attributes === null) {
                $invalid++;

                continue;
            }
            if (isset($seen[$attributes['dedup_key']])) {
                $duplicates++;

                continue;
            }

            $seen[$attributes['dedup_key']] = true;
            try {
                $rule->exercises()->create($attributes + [
                    'status' => GrammarRuleExercise::STATUS_DRAFT,
                    'origin' => $origin,
                ]);
            } catch (UniqueConstraintViolationException) {
                // A concurrent batch for this rule saved the same exercise first.
                $duplicates++;

                continue;
            }
            $created++;
        }

        if ($invalid + $duplicates > 0) {
            Log::info('GrammarExerciseDrafts: dropped generated exercises', [
                'grammar_rule_id' => $ruleId,
                'created' => $created,
                'invalid' => $invalid,
                'duplicates' => $duplicates,
            ]);
        }

        return $created;
    }

    public function existingPrompts(int $ruleId, int $limit = 60): array
    {
        return GrammarRuleExercise::query()
            ->where('grammar_rule_id', $ruleId)
            ->where('status', '!=', GrammarRuleExercise::STATUS_ARCHIVED)
            ->latest('id')
            ->limit($limit)
            ->pluck('prompt')
            ->all();
    }

    /** @return array<string, mixed>|null */
    private function validAttributes(mixed $item): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        $type = is_string($item['type'] ?? null) ? $item['type'] : null;
        $instruction = $this->text($item['instruction'] ?? null) ?: null;
        $prompt = $this->text($item['prompt'] ?? null);

        // A build exercise's prompt is only context; the tiles are the exercise.
        if ($type === GrammarRuleExercise::TYPE_BUILD && $prompt === '') {
            $prompt = $instruction ?? 'Put the words in order.';
        }

        if (! in_array($type, GrammarRuleExercise::TYPES, true) || $prompt === '') {
            return null;
        }

        $base = [
            'type' => $type,
            'prompt' => $prompt,
            'instruction' => $instruction,
            'explanation' => $this->text($item['explanation'] ?? null) ?: null,
        ];

        $specific = $type === GrammarRuleExercise::TYPE_MULTIPLE_CHOICE
            ? $this->choiceAttributes($item)
            : $this->textAnswerAttributes($type, $prompt, $item);

        if ($specific === null) {
            return null;
        }

        $answers = $specific['_answers'];
        unset($specific['_answers']);

        // Build prompts repeat ("Make a question"), so the sentence itself identifies the exercise.
        $dedupKey = $type === GrammarRuleExercise::TYPE_BUILD
            ? 'build:'.$this->checker->normalize($answers[0])
            : $this->checker->normalize($prompt);

        return $base + $specific + [
            'dedup_key' => mb_substr($dedupKey, 0, 255),
            'hint' => $this->safeHint($item['hint'] ?? null, $answers),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function choiceAttributes(array $item): ?array
    {
        $options = is_array($item['options'] ?? null)
            ? array_values(array_filter(array_map(fn ($o) => $this->text($o), $item['options']), fn (string $o) => $o !== ''))
            : [];
        $answerIndex = is_numeric($item['answer_index'] ?? null) ? (int) $item['answer_index'] : null;

        if (count($options) < 2 || count($options) > self::MAX_OPTIONS || $answerIndex === null || ! isset($options[$answerIndex])) {
            return null;
        }

        return ['options' => $options, 'answer_index' => $answerIndex, '_answers' => [$options[$answerIndex]]];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function textAnswerAttributes(string $type, string $prompt, array $item): ?array
    {
        $answer = $this->text($item['answer'] ?? null);
        if ($answer === '') {
            return null;
        }

        $accepted = is_array($item['accepted_answers'] ?? null)
            ? array_values(array_unique(array_filter(array_map(fn ($a) => $this->text($a), $item['accepted_answers']), fn (string $a) => $a !== '' && $a !== $answer)))
            : [];

        $attributes = ['answer' => $answer, 'accepted_answers' => $accepted ?: null, '_answers' => [$answer, ...$accepted]];

        // "Fix the mistake" whose answer equals the prompt has no mistake to fix.
        if ($type === GrammarRuleExercise::TYPE_FIX && $this->checker->typedMatches($prompt, $answer, [])) {
            return null;
        }

        if ($type === GrammarRuleExercise::TYPE_BUILD) {
            $tiles = is_array($item['tiles'] ?? null)
                ? array_values(array_filter(array_map(fn ($t) => $this->text($t), $item['tiles']), fn (string $t) => $t !== ''))
                : [];
            if (count($tiles) < 3 || ! $this->tilesSpellAnswer($tiles, $answer)) {
                return null;
            }
            $attributes['tiles'] = $tiles;
        }

        return $attributes;
    }

    /** The tiles, in some order, must contain exactly the answer's words. */
    private function tilesSpellAnswer(array $tiles, string $answer): bool
    {
        $words = fn (string $text): array => array_values(array_filter(
            explode(' ', preg_replace('/[.,!?;:]/u', '', $this->checker->normalize($text)) ?? ''),
            fn (string $w) => $w !== '',
        ));

        $tileWords = $words(implode(' ', $tiles));
        $answerWords = $words($answer);
        sort($tileWords);
        sort($answerWords);

        return $tileWords === $answerWords;
    }

    /** @param  list<string>  $answers */
    private function safeHint(mixed $hint, array $answers): ?string
    {
        $hint = $this->text($hint);
        if ($hint === '') {
            return null;
        }

        foreach ($answers as $answer) {
            if ($this->checker->hintRevealsAnswer($hint, $answer)) {
                return null;
            }
        }

        return $hint;
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
