<?php

namespace App\Modules\Learning\Application;

use App\Contracts\Ai\SpeakingMistakeRecorderInterface;
use App\Modules\Learning\Domain\Models\SpeakingMistake;

final class SpeakingMistakeRecorder implements SpeakingMistakeRecorderInterface
{
    public function recordWrongAnswer(int $userId, array $data): ?array
    {
        if (trim((string) ($data['original_text'] ?? '')) === '' || trim((string) ($data['corrected_text'] ?? '')) === '') {
            return null;
        }

        $mistake = SpeakingMistake::query()->where('user_id', $userId)
            ->where('language', $data['language'])
            ->where('original_text', $data['original_text'])
            ->where('corrected_text', $data['corrected_text'])
            ->where('status', 'active')->first();

        if ($mistake) {
            $mistake->forceFill(['last_seen_at' => now(), 'consecutive_correct' => 0])->save();
        } else {
            $mistake = SpeakingMistake::query()->create([
                'user_id' => $userId,
                ...$data,
                'category' => $data['category'] ?? 'general',
                'source_type' => $data['source_type'] ?? 'speaking_practice',
                'confidence' => 'clear',
                'status' => 'active',
                'last_seen_at' => now(),
            ]);
        }

        return ['id' => $mistake->id, 'status' => $mistake->status, 'saved_automatically' => true];
    }

    public function recordPracticeOutcome(int $userId, int $mistakeId, bool $correct): ?array
    {
        $mistake = SpeakingMistake::query()->where('user_id', $userId)->whereKey($mistakeId)
            ->where('status', 'active')->first();
        if (! $mistake) return null;

        $streak = $correct ? $mistake->consecutive_correct + 1 : 0;
        $mistake->forceFill([
            'consecutive_correct' => $streak,
            'status' => $streak >= 3 ? 'mastered' : 'active',
            'last_seen_at' => now(),
        ])->save();

        return ['id' => $mistake->id, 'status' => $mistake->status, 'consecutive_correct' => $streak];
    }
}
