<?php

namespace App\Modules\Content\Domain;

/**
 * Identity of a grammar rule's title (VIK-16): two rules in the same
 * language whose titles differ only in case, spacing or trailing
 * punctuation are the same rule ("First Conditional" = " first  conditional.").
 * Semantic near-duplicates ("Past Simple" vs "Simple Past Tense") are not
 * equal here; embeddings and the admin merge command cover those.
 */
final class GrammarRuleTitle
{
    public static function normalize(string $title): string
    {
        $title = mb_strtolower(str_replace(['’', '‘'], "'", $title));
        $title = (string) preg_replace('/\s+/u', ' ', $title);

        return trim($title, " \t\n\r\0\x0B.,;:!?");
    }
}
