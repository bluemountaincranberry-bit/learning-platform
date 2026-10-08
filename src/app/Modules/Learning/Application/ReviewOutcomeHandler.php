<?php

namespace App\Modules\Learning\Application;

use App\Modules\Content\Application\Contracts\LexemeIdentityReaderInterface;
use App\Modules\Content\Application\Contracts\SrsReviewReferenceReaderInterface;
use App\Modules\Learning\Domain\Events\ExerciseCompleted;
use App\Modules\Srs\Application\Contracts\ReviewOutcomeHandlerInterface;
use App\Modules\Srs\Application\Data\ReviewOutcome;

final class ReviewOutcomeHandler implements ReviewOutcomeHandlerInterface
{
    public function __construct(
        private readonly PointsAwardService $points,
        private readonly LearningRetryService $retries,
        private readonly LearningFlowMetricsService $metrics,
        private readonly SrsReviewReferenceReaderInterface $reviewReferences,
        private readonly LexemeIdentityReaderInterface $lexemes,
    ) {}

    public function handle(ReviewOutcome $outcome): void
    {
        $occurrence = $outcome->contentLexemeId !== null
            ? $this->reviewReferences->occurrenceContext($outcome->contentLexemeId)
            : null;

        $this->points->awardForReview(
            $outcome,
            $occurrence['id'] ?? null,
            $occurrence['language'] ?? null,
            $occurrence['level'] ?? null,
        );
        if ($outcome->isFailing && $occurrence !== null) {
            $this->retries->scheduleForReview($outcome->userId, $occurrence['id'], $occurrence['content_id'], $outcome);
        }

        $flow = app(LearningFlowResolver::class)->resolveForUserId($outcome->userId, $occurrence['language'] ?? null);
        $exerciseType = (string) ($outcome->exerciseType ?? 'review');
        $item = $outcome->lexemeId === null
            ? ''
            : ($this->lexemes->lemmasByIds([$outcome->lexemeId])[$outcome->lexemeId] ?? '');
        $dimension = match ($exerciseType) {
            'listening', 'dictation' => 'listening',
            'cloze', 'production' => 'production',
            'speaking', 'shadowing' => 'speaking',
            'listen-recognize' => 'recognition',
            default => 'recall',
        };

        $this->metrics->recordOutcome(
            $outcome->userId,
            $flow['profile']?->id,
            $occurrence['id'] ?? null,
            $exerciseType,
            ! $outcome->isFailing,
            $dimension,
            null,
            ['grade' => $outcome->grade, 'hint_used' => $outcome->hintUsed],
        );

        event(new ExerciseCompleted(
            userId: $outcome->userId,
            item: $item,
            grade: $outcome->grade,
            isMistake: $outcome->isFailing,
            lexemeId: $outcome->lexemeId,
        ));
    }
}
