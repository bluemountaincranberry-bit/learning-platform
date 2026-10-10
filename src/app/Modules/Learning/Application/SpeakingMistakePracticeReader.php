<?php

namespace App\Modules\Learning\Application;

use App\Contracts\Ai\SpeakingMistakePracticeReaderInterface;
use App\Modules\Learning\Domain\Models\SpeakingMistake;

final class SpeakingMistakePracticeReader implements SpeakingMistakePracticeReaderInterface
{
    public function cardsForUser(int $userId, array $mistakeIds, int $count): array
    {
        return SpeakingMistake::query()->where('user_id', $userId)->where('status', 'active')
            ->whereIn('id', array_slice(array_values(array_unique(array_map('intval', $mistakeIds))), 0, 50))
            ->orderBy('last_seen_at')->limit($count)->get()
            ->map(fn (SpeakingMistake $mistake): array => [
                'prompt_sentence' => $mistake->prompt_text ?: $mistake->explanation ?: $mistake->original_text,
                'answer_sentence' => $mistake->corrected_text,
                'prompt_language' => $mistake->native_language,
                'answer_language' => $mistake->language,
                'hint_words' => [],
                'mistake_id' => $mistake->id,
            ])->all();
    }
}
