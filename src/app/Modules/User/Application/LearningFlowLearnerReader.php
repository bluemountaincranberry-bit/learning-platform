<?php

namespace App\Modules\User\Application;

use App\Modules\User\Application\Contracts\LearningFlowLearnerReaderInterface;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserLearningPreference;

final class LearningFlowLearnerReader implements LearningFlowLearnerReaderInterface
{
    public function forUser(int $userId): array
    {
        $user = User::query()->findOrFail($userId, ['id', 'translation_language', 'current_level', 'learning_goal']);
        $preferences = UserLearningPreference::query()->where('user_id', $userId)->first();

        return [
            'translation_language' => $user->translation_language,
            'current_level' => $user->current_level,
            'learning_goal' => $user->learning_goal,
            'learning_flow_profile_id' => $preferences?->learning_flow_profile_id,
            'preferences' => $preferences?->only([
                'session_minutes', 'daily_new_words', 'listening_weight', 'speaking_weight',
                'hint_mode', 'difficulty_preference',
            ]) ?? [],
        ];
    }
}
