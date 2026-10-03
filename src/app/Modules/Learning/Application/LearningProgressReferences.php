<?php

namespace App\Modules\Learning\Application;

use App\Modules\Content\Application\Contracts\LearningProgressReferencesInterface;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;

class LearningProgressReferences implements LearningProgressReferencesInterface
{
    public function referencedLexemeIds(array $lexemeIds): array
    {
        if ($lexemeIds === []) {
            return [];
        }

        return UserLexemeProgress::query()
            ->whereIn('lexeme_id', $lexemeIds)
            ->distinct()
            ->pluck('lexeme_id')
            ->all();
    }

    public function hasContentLexeme(int $contentLexemeId): bool
    {
        return UserLexemeProgress::query()
            ->where('content_lexeme_id', $contentLexemeId)
            ->exists();
    }
}
