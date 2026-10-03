<?php

namespace App\Modules\Srs\Application;

use App\Modules\Srs\Application\Contracts\ReviewGradePolicyInterface;
use App\Modules\Srs\Application\Contracts\ReviewMistakesReaderInterface;
use App\Modules\Srs\Domain\Models\SrsReview;

final class ReviewMistakesReader implements ReviewMistakesReaderInterface
{
    public function __construct(private readonly ReviewGradePolicyInterface $grades) {}

    public function recentForUser(int $userId, int $limit): array
    {
        $mistakes = SrsReview::query()
            ->join('srs_cards', 'srs_cards.id', '=', 'srs_reviews.srs_card_id')
            ->where('srs_cards.user_id', $userId)
            ->where('srs_reviews.grade', '<=', $this->grades->failingThreshold())
            ->orderByDesc('srs_reviews.reviewed_at')
            ->limit($limit)
            ->select('srs_reviews.grade', 'srs_reviews.reviewed_at', 'srs_cards.item_key')
            ->get();

        if ($mistakes->isEmpty()) {
            return ['mistake_count' => 0, 'mistakes' => [], 'note' => 'No failed reviews yet.'];
        }

        return [
            'mistake_count' => $mistakes->count(),
            'mistakes' => $mistakes->map(fn (SrsReview $mistake): array => [
                'item' => (string) preg_replace('/^(word|phrase):/', '', $mistake->item_key),
                'grade' => (int) $mistake->grade,
                'reviewed_at' => $mistake->reviewed_at?->toIso8601String(),
            ])->all(),
        ];
    }
}
