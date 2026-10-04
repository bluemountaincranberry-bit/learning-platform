<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\GrammarRuleExampleWriterInterface;
use App\Modules\Content\Application\Data\GrammarRuleExampleSource;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarRuleExample;
use Illuminate\Support\Facades\DB;

final class GrammarRuleExampleWriter implements GrammarRuleExampleWriterInterface
{
    private const MAX_LENGTH = 200;

    public function source(int $ruleId): GrammarRuleExampleSource
    {
        $rule = GrammarRule::query()->with('topic')->findOrFail($ruleId);

        return new GrammarRuleExampleSource(
            ruleId: $rule->id,
            title: $rule->title,
            topicName: $rule->topic?->name ?? '',
            language: $rule->language ?? 'en',
            level: $rule->level,
            summary: $rule->summary ?? '',
            body: $rule->body ?? '',
            existingExamples: $rule->examples()->pluck('example')->filter()->values()->all(),
        );
    }

    public function append(int $ruleId, array $items, ?string $translationLanguage, int $limit): int
    {
        $rule = GrammarRule::query()->findOrFail($ruleId);

        return DB::transaction(function () use ($rule, $items, $translationLanguage, $limit): int {
            $seen = array_flip($rule->examples()->pluck('example')->map(fn ($text) => GrammarRuleExampleMarkup::dedupKey((string) $text))->all());
            $sortOrder = (int) $rule->examples()->max('sort_order');
            $created = 0;

            foreach ($items as $item) {
                if ($created >= $limit) {
                    break;
                }

                $attributes = $this->validAttributes($item, $translationLanguage);
                if ($attributes === null) {
                    continue;
                }

                $key = GrammarRuleExampleMarkup::dedupKey($attributes['example']);
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $sortOrder += 10;
                $rule->examples()->create($attributes + [
                    'language' => $rule->language ?? 'en',
                    'origin' => GrammarRuleExample::ORIGIN_AI,
                    'is_primary' => false,
                    'sort_order' => $sortOrder,
                ]);
                $created++;
            }

            return $created;
        });
    }

    /** @return array<string, mixed>|null */
    private function validAttributes(mixed $item, ?string $translationLanguage): ?array
    {
        if (! is_array($item) || ! is_string($item['text'] ?? null)) {
            return null;
        }

        $parsed = GrammarRuleExampleMarkup::parse($item['text']);
        if ($parsed === null || trim($parsed['text']) === '' || mb_strlen($parsed['text']) > self::MAX_LENGTH) {
            return null;
        }

        $kind = in_array($item['kind'] ?? null, GrammarRuleExample::KINDS, true) ? $item['kind'] : null;
        // `wrong` is the prompt's field; `mistake` is accepted for older prompt overrides.
        $wrong = $item['wrong'] ?? $item['mistake'] ?? null;
        $mistake = is_string($wrong) ? GrammarRuleExampleMarkup::strip($wrong) : '';

        // A "mistake" example without a real wrong sentence is just an example.
        if ($kind === GrammarRuleExample::KIND_MISTAKE && ($mistake === '' || mb_strlen($mistake) > self::MAX_LENGTH
            || GrammarRuleExampleMarkup::dedupKey($mistake) === GrammarRuleExampleMarkup::dedupKey($parsed['text']))) {
            $kind = null;
        }

        $translation = is_string($item['translation'] ?? null) ? GrammarRuleExampleMarkup::strip($item['translation']) : '';
        $translate = $translationLanguage !== null && $translation !== '';

        return [
            'example' => $parsed['text'],
            'target_spans' => $parsed['spans'],
            'kind' => $kind,
            'mistake' => $kind === GrammarRuleExample::KIND_MISTAKE ? $mistake : null,
            'translation' => $translate ? $translation : null,
            'translation_language' => $translate ? $translationLanguage : null,
        ];
    }
}
