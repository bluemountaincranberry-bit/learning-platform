<?php

namespace App\Modules\Content\Application\Contracts;

use App\Modules\Content\Application\Data\GrammarRuleExampleSource;

/**
 * Port for the AI example generator: reads what it needs about a rule and
 * appends the generated examples. Content owns validation (marked target
 * form, length, duplicates), so a bad model answer never reaches the catalog.
 */
interface GrammarRuleExampleWriterInterface
{
    public function source(int $ruleId): GrammarRuleExampleSource;

    /**
     * Appends valid, non-duplicate items after the rule's existing examples
     * as origin = ai. Each item: text with the target form wrapped in
     * `**…**` (always the correct sentence), kind, translation, and
     * `wrong` (the learner's typical error) for kind = mistake.
     *
     * @param  array<int, mixed>  $items
     * @return int how many were stored (at most $limit)
     */
    public function append(int $ruleId, array $items, ?string $translationLanguage, int $limit): int;
}
