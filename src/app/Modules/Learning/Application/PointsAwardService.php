<?php

namespace App\Modules\Learning\Application;

use App\Modules\Learning\Domain\Models\ExerciseAttempt;
use App\Modules\Learning\Domain\Models\LearningPointEvent;
use App\Modules\Srs\Application\Data\ReviewOutcome;

class PointsAwardService
{
    /** @return array{points: int, created: bool} */
    public function awardForAttempt(ExerciseAttempt $attempt, int $userId, ?int $contentLexemeId, ?string $contentLanguage, ?string $contentLevel): array
    {
        return $this->award(
            userId: $userId,
            contentLexemeId: $contentLexemeId,
            sourceType: 'exercise_attempt',
            sourceId: $attempt->id,
            activity: $attempt->exercise_type,
            score: (int) ($attempt->score ?? 0),
            hintUsed: (bool) $attempt->hint_used,
            language: $contentLanguage,
            level: $contentLevel,
        );
    }

    /** @return array{points: int, created: bool} */
    public function awardForReview(ReviewOutcome $review, ?int $contentLexemeId, ?string $contentLanguage, ?string $contentLevel): array
    {
        return $this->award(
            userId: $review->userId,
            contentLexemeId: $contentLexemeId,
            sourceType: 'srs_review',
            sourceId: $review->reviewId,
            activity: (string) ($review->exerciseType ?: 'recall'),
            score: $review->isFailing ? 0 : 100,
            hintUsed: (bool) $review->hintUsed,
            language: $contentLanguage,
            level: $contentLevel,
        );
    }

    /** @return array{points: int, created: bool} */
    private function award(int $userId, ?int $contentLexemeId, string $sourceType, int $sourceId, string $activity, int $score, bool $hintUsed, ?string $language, ?string $level): array
    {
        $sourceKey = "{$sourceType}:{$sourceId}";
        $flow = app(LearningFlowResolver::class)->resolveForUserId($userId, $language);
        $base = $this->basePoints($activity, (array) ($flow['config']['points'] ?? []));
        $quality = $score >= 90 ? 1.0 : ($score >= 60 ? 0.6 : 0.0);
        if ($hintUsed) {
            $quality *= 0.6;
        }
        $multiplier = (float) (($flow['config']['difficulty_multipliers'] ?? [])[$level] ?? 1.0);
        $points = (int) round($base * $multiplier * $quality);

        $event = LearningPointEvent::query()->firstOrCreate(
            ['source_key' => $sourceKey],
            [
                'user_id' => $userId,
                'content_lexeme_id' => $contentLexemeId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'activity_type' => $activity,
                'reason' => $score >= 90 ? 'successful_retrieval' : ($score > 0 ? 'partial_retrieval' : 'completed_attempt'),
                'base_points' => $base,
                'multiplier' => $multiplier,
                'points' => $points,
                'metadata' => ['score' => $score, 'hint_used' => $hintUsed, 'level' => $level],
            ],
        );

        return ['points' => (int) $event->points, 'created' => $event->wasRecentlyCreated];
    }

    /** @param array<string, mixed> $configured */
    private function basePoints(string $activity, array $configured): int
    {
        return (int) ($configured[$activity] ?? LearningFlowDefaults::balanced()['points'][$activity]
            ?? match ($activity) {
                'review', 'recall', 'self_check' => 10,
                'quick-check', 'recognition' => 5,
                default => 10,
            });
    }
}
