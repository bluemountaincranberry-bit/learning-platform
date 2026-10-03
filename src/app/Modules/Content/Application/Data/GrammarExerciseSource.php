<?php

namespace App\Modules\Content\Application\Data;

final readonly class GrammarExerciseSource
{
    public function __construct(
        public int $ruleId,
        public string $title,
        public string $topicName,
        public string $language,
        public string $summary,
        public string $body,
        public string $examples,
    ) {}
}
