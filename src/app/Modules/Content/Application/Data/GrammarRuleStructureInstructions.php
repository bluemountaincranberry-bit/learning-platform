<?php

namespace App\Modules\Content\Application\Data;

final class GrammarRuleStructureInstructions
{
    public static function text(): string
    {
        return 'Structure the body as markdown with these headings, in this order: '
            .'"## Rule" (a clear one-paragraph statement of the rule), '
            .'"## Formation" (how it is built, with a pattern such as "subject + have/has + been + verb-ing"), '
            .'"## Usage" (when to use it, as a short bullet list of cases), '
            .'"## Common mistakes" (a short bullet list of frequent learner errors). '
            .'Keep it concise — this is a quick-reference card, not an essay.';
    }
}
