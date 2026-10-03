<?php

namespace App\Modules\Learning\Application;

use App\Modules\Content\Application\Contracts\ContentLearnerStateReaderInterface;
use App\Modules\Learning\Domain\Models\UserLexemeConfidence;
use App\Modules\Learning\Domain\Models\UserLexemeContextCheck;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use App\Modules\Learning\Domain\Models\UserLexemeSkip;

class ContentLearnerStateReader implements ContentLearnerStateReaderInterface
{
    public function lexemeState(int $userId, array $contentLexemeIds): array
    {
        $confidence = UserLexemeConfidence::query()
            ->where('user_id', $userId)
            ->whereIn('content_lexeme_id', $contentLexemeIds)
            ->get()
            ->mapWithKeys(fn (UserLexemeConfidence $row): array => [
                $row->content_lexeme_id => [
                    'recognition' => (int) $row->recognition,
                    'recall' => (int) $row->recall,
                    'production' => (int) $row->production,
                    'listening' => (int) $row->listening,
                    'speaking' => (int) $row->speaking,
                ],
            ])
            ->all();

        return [
            'learned' => UserLexemeProgress::query()->where('user_id', $userId)->pluck('lexeme_id')->all(),
            'skipped' => UserLexemeSkip::query()->where('user_id', $userId)->pluck('lexeme_id')->all(),
            'needs_context_review' => UserLexemeContextCheck::query()
                ->where('user_id', $userId)
                ->where('last_result', UserLexemeContextCheck::RESULT_NEEDS_WORK)
                ->pluck('lexeme_id')
                ->all(),
            'confidence' => $confidence,
        ];
    }

    public function learnedCountsByContent(int $userId, array $contentIds): array
    {
        if ($contentIds === []) {
            return [];
        }

        return UserLexemeProgress::query()
            ->where('user_id', $userId)
            ->join('content_lexemes', 'content_lexemes.lexeme_id', '=', 'user_lexeme_progress.lexeme_id')
            ->whereIn('content_lexemes.content_id', $contentIds)
            ->groupBy('content_lexemes.content_id')
            ->selectRaw('content_lexemes.content_id as content_id, count(distinct content_lexemes.lexeme_id) as learned')
            ->pluck('learned', 'content_id')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }
}
