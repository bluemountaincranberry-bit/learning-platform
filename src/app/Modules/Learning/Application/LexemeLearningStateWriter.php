<?php

namespace App\Modules\Learning\Application;

use App\Modules\Content\Application\Contracts\LexemeLearningStateWriterInterface;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use App\Modules\Learning\Domain\Models\UserLexemeSkip;

class LexemeLearningStateWriter implements LexemeLearningStateWriterInterface
{
    public function markLearned(int $userId, int $lexemeId, int $contentLexemeId): void
    {
        UserLexemeProgress::query()->updateOrCreate(
            ['user_id' => $userId, 'lexeme_id' => $lexemeId],
            ['content_lexeme_id' => $contentLexemeId, 'learned_at' => now()],
        );
    }

    public function unmarkLearned(int $userId, int $lexemeId): void
    {
        UserLexemeProgress::query()
            ->where('user_id', $userId)
            ->where('lexeme_id', $lexemeId)
            ->delete();
    }

    public function skip(int $userId, int $lexemeId): void
    {
        UserLexemeSkip::query()->firstOrCreate([
            'user_id' => $userId,
            'lexeme_id' => $lexemeId,
        ]);
    }

    public function unskip(int $userId, int $lexemeId): void
    {
        UserLexemeSkip::query()
            ->where('user_id', $userId)
            ->where('lexeme_id', $lexemeId)
            ->delete();
    }
}
