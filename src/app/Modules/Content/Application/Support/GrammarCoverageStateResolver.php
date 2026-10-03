<?php

namespace App\Modules\Content\Application\Support;

class GrammarCoverageStateResolver
{
    public static function forTopic(int $rulesCount, int $coveredRulesCount): string
    {
        if ($rulesCount === 0) {
            return 'empty';
        }

        return $rulesCount === $coveredRulesCount ? 'covered' : 'needs_coverage';
    }

    public static function forRule(int $examplesCount, int $lexemesCount, int $contentLinksCount): string
    {
        if ($examplesCount === 0) {
            return 'needs_examples';
        }

        if ($lexemesCount === 0) {
            return 'needs_lexemes';
        }

        if ($contentLinksCount === 0) {
            return 'needs_content';
        }

        return 'covered';
    }

    public static function forLexeme(int $examplesCount, int $rulesCount, int $contentLinksCount): string
    {
        if ($examplesCount === 0) {
            return 'needs_examples';
        }

        if ($rulesCount === 0) {
            return 'needs_rules';
        }

        if ($contentLinksCount === 0) {
            return 'needs_content';
        }

        return 'covered';
    }
}
