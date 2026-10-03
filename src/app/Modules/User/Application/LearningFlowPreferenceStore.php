<?php

namespace App\Modules\User\Application;

use App\Modules\User\Application\Contracts\LearningFlowPreferenceStoreInterface;
use App\Modules\User\Models\UserLearningPreference;

final class LearningFlowPreferenceStore implements LearningFlowPreferenceStoreInterface
{
    public function forUser(int $userId): ?array
    {
        return UserLearningPreference::query()->where('user_id', $userId)->first()?->toArray();
    }

    public function updateForUser(int $userId, array $preferences): array
    {
        return UserLearningPreference::query()->updateOrCreate(
            ['user_id' => $userId],
            $preferences,
        )->toArray();
    }
}
