<?php

namespace App\Modules\Content\Application\Data;

/** What the example generator needs to know about a rule. */
final readonly class GrammarRuleExampleSource
{
    /**
     * @param  list<string>  $existingExamples  plain sentences already on the rule, so the model does not repeat them
     */
    public function __construct(
        public int $ruleId,
        public string $title,
        public string $topicName,
        public string $language,
        public ?string $level,
        public string $summary,
        public string $body,
        public array $existingExamples,
    ) {}
}
