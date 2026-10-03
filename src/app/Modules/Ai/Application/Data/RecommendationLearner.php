<?php

namespace App\Modules\Ai\Application\Data;

final readonly class RecommendationLearner
{
    public function __construct(
        public int $userId,
        public ?string $uiLanguage,
        public ?string $translationLanguage,
        public ?string $learningGoal,
        public ?string $currentLevel,
    ) {}

    public static function fromUser(object $user): self
    {
        return new self(
            userId: (int) $user->id,
            uiLanguage: $user->ui_language,
            translationLanguage: $user->translation_language,
            learningGoal: $user->learning_goal,
            currentLevel: $user->current_level,
        );
    }
}
