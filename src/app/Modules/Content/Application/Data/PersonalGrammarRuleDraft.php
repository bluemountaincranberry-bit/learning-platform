<?php

namespace App\Modules\Content\Application\Data;

final readonly class PersonalGrammarRuleDraft
{
    public function __construct(
        public int $ownerUserId,
        public int $sourceLessonId,
        public string $sourceLessonTitle,
        public string $language,
        public string $title,
        public ?string $summary,
        public ?string $body,
        public ?string $example,
        public ?string $exampleTranslation,
    ) {}
}
