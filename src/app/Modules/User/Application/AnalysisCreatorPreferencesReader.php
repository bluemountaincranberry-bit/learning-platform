<?php

namespace App\Modules\User\Application;

use App\Modules\User\Application\Contracts\AnalysisCreatorPreferencesReaderInterface;
use App\Modules\User\Models\User;

final class AnalysisCreatorPreferencesReader implements AnalysisCreatorPreferencesReaderInterface
{
    public function forUser(int $userId): ?array
    {
        $user = User::query()->find($userId, ['current_level', 'translation_language', 'ai_extraction_thoroughness']);

        if ($user === null) {
            return null;
        }

        return [
            'current_level' => $user->current_level,
            'translation_language' => $user->translation_language,
            'ai_extraction_thoroughness' => $user->ai_extraction_thoroughness,
        ];
    }
}
