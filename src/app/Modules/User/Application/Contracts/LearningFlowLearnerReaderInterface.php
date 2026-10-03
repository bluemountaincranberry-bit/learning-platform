<?php

namespace App\Modules\User\Application\Contracts;

interface LearningFlowLearnerReaderInterface
{
    /**
     * @return array{translation_language: ?string, current_level: ?string, learning_goal: ?string, learning_flow_profile_id: ?int, preferences: array<string, mixed>}
     */
    public function forUser(int $userId): array;
}
