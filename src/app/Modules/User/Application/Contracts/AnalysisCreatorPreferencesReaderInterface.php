<?php

namespace App\Modules\User\Application\Contracts;

interface AnalysisCreatorPreferencesReaderInterface
{
    /** @return array{current_level: ?string, translation_language: ?string, ai_extraction_thoroughness: ?string}|null */
    public function forUser(int $userId): ?array;
}
