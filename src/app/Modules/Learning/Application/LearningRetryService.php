<?php

namespace App\Modules\Learning\Application;

use App\Modules\Learning\Domain\Models\ExerciseAttempt;
use App\Modules\Learning\Domain\Models\LearningRetry;
use App\Modules\Srs\Application\Data\ReviewOutcome;
use Illuminate\Support\Collection;

class LearningRetryService
{
    public function scheduleForExercise(ExerciseAttempt $attempt, int $userId, int $contentLexemeId, int $contentId): LearningRetry
    {
        return $this->schedule($userId, $contentLexemeId, $contentId, $attempt->exercise_type, $attempt->error_type, [
            'source_exercise_attempt_id' => $attempt->id,
        ]);
    }

    public function scheduleForReview(int $userId, int $contentLexemeId, int $contentId, ReviewOutcome $review): LearningRetry
    {
        return $this->schedule($userId, $contentLexemeId, $contentId, (string) ($review->exerciseType ?: 'recall'), $review->errorType, [
            'source_srs_review_id' => $review->reviewId,
        ]);
    }

    /** @param array<string, mixed> $source */
    public function schedule(int $userId, int $contentLexemeId, int $contentId, string $activity, ?string $errorType, array $source = []): LearningRetry
    {
        $retry = LearningRetry::query()->where('user_id', $userId)
            ->where('content_lexeme_id', $contentLexemeId)
            ->where('status', 'pending')
            ->first();

        $values = [
            'content_id' => $contentId,
            'activity_type' => $activity,
            'error_type' => $errorType,
            'available_at' => now()->addMinutes(5),
            'status' => 'pending',
            'metadata' => ['retry_delay_minutes' => 5],
            ...$source,
        ];

        if ($retry !== null) {
            $retry->update([
                ...$values,
                'attempt_count' => min(10, (int) $retry->attempt_count + 1),
            ]);

            return $retry->fresh();
        }

        return LearningRetry::query()->create([
            'user_id' => $userId,
            'content_lexeme_id' => $contentLexemeId,
            'attempt_count' => 1,
            ...$values,
        ]);
    }

    public function resolve(int $retryId, int $userId, bool $success): void
    {
        $retry = LearningRetry::query()->where('user_id', $userId)->find($retryId);
        if ($retry === null) {
            return;
        }

        if ($success) {
            $retry->update(['status' => 'completed']);

            return;
        }

        $retry->update(['status' => 'pending', 'available_at' => now()->addMinutes(5), 'attempt_count' => min(10, (int) $retry->attempt_count + 1)]);
    }

    /** @return Collection<int, LearningRetry> */
    public function due(int $userId, ?int $contentId = null, int $limit = 10): Collection
    {
        return LearningRetry::query()
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->where('available_at', '<=', now())
            ->when($contentId !== null, fn ($query) => $query->where('content_id', $contentId))
            ->orderBy('available_at')
            ->limit($limit)
            ->get();
    }
}
