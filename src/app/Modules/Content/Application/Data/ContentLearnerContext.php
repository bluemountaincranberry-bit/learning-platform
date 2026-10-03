<?php

namespace App\Modules\Content\Application\Data;

final readonly class ContentLearnerContext
{
    public function __construct(
        public int $userId,
        public ?string $translationLanguage = null,
        public ?string $learningGoal = null,
        public ?string $currentLevel = null,
    ) {}
}
