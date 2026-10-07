<?php

namespace App\Modules\Srs\Application;

use App\Modules\Content\Application\Contracts\SrsReviewReferenceReaderInterface;
use App\Modules\Srs\Application\Contracts\ReviewOutcomeHandlerInterface;
use App\Modules\Srs\Application\Contracts\SrsRepositoryInterface;
use App\Modules\Srs\Application\Contracts\SrsServiceInterface;
use App\Modules\Srs\Application\Data\ReviewOutcome;
use App\Modules\Srs\Domain\IntervalCalculator;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\Srs\Domain\ReviewGradeRules;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;

class SrsService implements SrsServiceInterface
{
    public function __construct(
        private readonly SrsRepositoryInterface $repository,
        private readonly ReviewOutcomeHandlerInterface $reviewOutcomeHandler,
        private readonly SrsReviewReferenceReaderInterface $reviewReferences,
    ) {}

    /**
     * Same "grade <= 2 is a failure" convention already used by
     * GetUserMistakesTool/GetWeakTopicsTool (App\Modules\Ai) — duplicated
     * as a local constant rather than importing across module boundaries
     * (Srs -> Ai), since Ai already depends on Srs the other way for its
     * read-only tools.
     */
    public function getInReviewLexemeIds(int $userId): BaseCollection
    {
        return $this->repository->getInReviewLexemeIds($userId);
    }

    public function getDueCards(int $userId): Collection
    {
        return $this->repository->getDueCards($userId);
    }

    public function reviewCard(int $cardId, int $grade, int $userId, IntervalCalculator $calculator, array $context = []): SrsCard
    {
        return DB::transaction(function () use ($cardId, $grade, $userId, $calculator, $context): SrsCard {
            $card = $this->repository->findCardForUserOrFail($cardId, $userId);

            $prevInterval = $card->interval_days;
            $decision = $calculator->calculate($prevInterval, (float) $card->ease_factor, $grade);
            $newEase = $decision->easeFactor;
            $newInterval = $decision->intervalDays;

            $card = $this->repository->updateCard($card, [
                'interval_days' => $newInterval,
                'ease_factor' => $newEase,
                'state' => ReviewGradeRules::isFailing($grade) ? 'relearning' : 'reviewing',
                'next_review_at' => now()->addDays($newInterval),
            ]);

            $review = $this->repository->createReview([
                'srs_card_id' => $card->id,
                'grade' => $grade,
                'prev_interval' => $prevInterval,
                'new_interval' => $newInterval,
                'content_lexeme_id' => $context['content_lexeme_id'] ?? ($card->content_id !== null && $card->item_key !== null
                    ? $this->reviewReferences->lexemeIdForItemKey($card->content_id, $card->item_key)
                    : null),
                'transcript_segment_id' => $context['transcript_segment_id'] ?? null,
                'exercise_type' => $context['exercise_type'] ?? 'srs_review',
                'error_type' => $context['error_type'] ?? (ReviewGradeRules::isFailing($grade) ? 'incorrect' : null),
                'hint_used' => array_key_exists('hint_used', $context) ? (bool) $context['hint_used'] : null,
                'answer_metadata' => $context['answer_metadata'] ?? null,
                'reviewed_at' => now(),
            ]);

            $this->reviewOutcomeHandler->handle(new ReviewOutcome(
                reviewId: (int) $review->id,
                userId: (int) $card->user_id,
                lexemeId: $card->lexeme_id !== null ? (int) $card->lexeme_id : null,
                itemKey: $card->item_key,
                contentLexemeId: $review->content_lexeme_id !== null ? (int) $review->content_lexeme_id : null,
                grade: (int) $review->grade,
                isFailing: ReviewGradeRules::isFailing((int) $review->grade),
                exerciseType: $review->exercise_type,
                errorType: $review->error_type,
                hintUsed: $review->hint_used,
            ));

            return $card;
        });
    }
}
