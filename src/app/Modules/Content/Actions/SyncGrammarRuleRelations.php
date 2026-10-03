<?php

namespace App\Modules\Content\Actions;

use App\Modules\Content\Domain\Models\GrammarRule;

final class SyncGrammarRuleRelations
{
    public function examples(GrammarRule $rule, array $examples): void
    {
        $rule->examples()->delete();

        foreach (array_values($examples) as $index => $example) {
            $rule->examples()->create([
                'language' => $example['language'] ?? $rule->language,
                'example' => $example['example'],
                'translation' => $example['translation'] ?? null,
                'is_primary' => (bool) ($example['is_primary'] ?? false),
                'sort_order' => $example['sort_order'] ?? (($index + 1) * 10),
            ]);
        }
    }

    public function lexemes(GrammarRule $rule, array $lexemeIds): void
    {
        $relations = [];

        foreach (array_values($lexemeIds) as $index => $lexemeId) {
            $relations[$lexemeId] = ['sort_order' => ($index + 1) * 10];
        }

        $rule->lexemes()->sync($relations);
    }
}
