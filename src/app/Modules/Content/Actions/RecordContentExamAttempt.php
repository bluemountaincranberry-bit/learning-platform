<?php

namespace App\Modules\Content\Actions;

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentExamAttempt;

final class RecordContentExamAttempt
{
    /**
     * @param  list<array{prompt_sentence: string, prompt_language: string, answer_language: string, answer: string, correct: bool, model_answer: string, hint_words: list<string>}>  $cardResults
     */
    public function execute(int $userId, Content $content, array $cardResults): ContentExamAttempt
    {
        $totalCards = count($cardResults);
        $correctAnswers = count(array_filter($cardResults, fn (array $cardResult): bool => (bool) ($cardResult['correct'] ?? false)));
        $scorePercentage = $totalCards > 0 ? round(($correctAnswers / $totalCards) * 100, 2) : 0.0;
        $passThresholdPercentage = (float) config('ai.exam.pass_threshold_pct', 80);

        return ContentExamAttempt::query()->create([
            'user_id' => $userId,
            'content_id' => $content->id,
            'total_cards' => $totalCards,
            'correct_count' => $correctAnswers,
            'score_pct' => $scorePercentage,
            'passed' => $scorePercentage >= $passThresholdPercentage,
            'pass_threshold_pct' => $passThresholdPercentage,
            'items' => array_values($cardResults),
            'completed_at' => now(),
        ]);
    }
}
