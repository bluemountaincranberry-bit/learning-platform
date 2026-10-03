<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\LexemeServiceInterface;
use App\Modules\Content\Contracts\Events\LexemeLearningStarted;
use App\Modules\Content\Contracts\Events\LexemeLearningStopped;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Application\Contracts\LexemeLearningStateWriterInterface;

class LexemeService implements LexemeServiceInterface
{
    public function __construct(private readonly LexemeLearningStateWriterInterface $learningState) {}

    public function markLearned(ContentLexeme $lexeme, int $userId): void
    {
        if ($lexeme->lexeme_id === null) {
            app(CanonicalLexemeSyncService::class)->sync($lexeme);
            $lexeme->refresh();
        }

        $this->learningState->markLearned($userId, $lexeme->lexeme_id, $lexeme->id);
    }

    public function unmarkLearned(ContentLexeme $lexeme, int $userId): void
    {
        $this->learningState->unmarkLearned($userId, $lexeme->lexeme_id);
    }

    public function startLearning(ContentLexeme $lexeme, int $userId): void
    {
        if ($lexeme->canonicalLexeme()->first() === null) {
            app(CanonicalLexemeSyncService::class)->sync($lexeme);
        }

        LexemeLearningStarted::dispatch($userId, $lexeme->id, "{$lexeme->type}:{$lexeme->text}", $lexeme->content_id);
    }

    public function stopLearning(ContentLexeme $lexeme, int $userId): void
    {
        LexemeLearningStopped::dispatch($userId, $lexeme->id, "{$lexeme->type}:{$lexeme->text}");
    }

    public function skip(ContentLexeme $lexeme, int $userId): void
    {
        if ($lexeme->lexeme_id === null) {
            app(CanonicalLexemeSyncService::class)->sync($lexeme);
            $lexeme->refresh();
        }

        $this->learningState->skip($userId, $lexeme->lexeme_id);
    }

    public function unskip(ContentLexeme $lexeme, int $userId): void
    {
        $this->learningState->unskip($userId, $lexeme->lexeme_id);
    }
}
