<?php

namespace App\Modules\Learning\Interfaces\Listeners;

use App\Modules\Content\Contracts\Events\LexemeLearningStarted;
use App\Modules\Learning\Domain\Models\UserLexemeSource;
use RuntimeException;

final class RecordUserLexemeSourceOnLearningStarted
{
    public function handle(LexemeLearningStarted $event): void
    {
        $source = UserLexemeSource::query()->firstOrNew([
            'user_id' => $event->userId,
            'content_lexeme_id' => $event->contentLexemeId,
        ]);

        if ($source->exists && (int) $source->lexeme_id !== $event->lexemeId) {
            throw new RuntimeException('The selected source is already linked to a different canonical lexeme.');
        }

        if ($source->exists) {
            return;
        }

        $source->fill([
            'lexeme_id' => $event->lexemeId,
            'source_kind' => 'content',
            'lesson_lexeme_candidate_id' => null,
            'source_text' => $event->sourceText,
            'source_example' => null,
            'display_label_snapshot' => $event->displayLabelSnapshot,
        ])->save();
    }
}
