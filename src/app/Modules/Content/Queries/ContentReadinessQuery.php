<?php

namespace App\Modules\Content\Queries;

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentExamAttempt;
use App\Modules\Content\Application\Contracts\ContentReadinessProgressInterface;

/**
 * "Ready to watch" quest status for one (user, content) pair: two steps
 * derived live from existing progress tables (words learned, grammar
 * learned — no new state, so this never needs invalidating when a learner
 * un-marks something later) plus the exam attempt log, which *is* new
 * state (ContentExamAttempt) since "passed an exam" isn't derivable from
 * anything that already exists. "Ready" = does a passed attempt exist —
 * derived from the log, the same way SrsCard.state is derived from
 * srs_reviews rather than hand-maintained.
 */
final class ContentReadinessQuery
{
    public function __construct(private readonly ContentReadinessProgressInterface $learningProgress) {}

    /**
     * @return array{words: array{total: int, learned: int, complete: bool}, grammar: array{total: int, learned: int, complete: bool}, exam_unlocked: bool}
     */
    public function stepsStatus(int $userId, Content $content): array
    {
        $lexemeIds = $content->lexemes()->whereNotNull('lexeme_id')->pluck('lexeme_id')->unique();
        $wordsTotal = $lexemeIds->count();
        $ruleIds = $content->grammarRules()->pluck('grammar_rules.id');
        $grammarTotal = $ruleIds->count();
        $learned = $this->learningProgress->learnedCounts($userId, $lexemeIds->values()->all(), $ruleIds->all());
        $wordsLearned = $learned['words_learned'];
        $wordsComplete = $wordsTotal > 0 && $wordsLearned >= $wordsTotal;

        $grammarLearned = $learned['grammar_learned'];
        // No grammar linked to this content at all -> trivially complete,
        // the exam just won't draw on any grammar points for it.
        $grammarComplete = $grammarTotal === 0 || $grammarLearned >= $grammarTotal;

        return [
            'words' => ['total' => $wordsTotal, 'learned' => $wordsLearned, 'complete' => $wordsComplete],
            'grammar' => ['total' => $grammarTotal, 'learned' => $grammarLearned, 'complete' => $grammarComplete],
            // Requires at least one word (grammar alone isn't enough to build
            // a meaningful exam) — content with zero words never unlocks one.
            'exam_unlocked' => $wordsTotal > 0 && $wordsComplete && $grammarComplete,
        ];
    }

    public function latestAttempt(int $userId, Content $content): ?ContentExamAttempt
    {
        return ContentExamAttempt::query()
            ->where('user_id', $userId)
            ->where('content_id', $content->id)
            ->orderByDesc('completed_at')
            ->first();
    }

    public function isReady(int $userId, Content $content): bool
    {
        return ContentExamAttempt::query()
            ->where('user_id', $userId)
            ->where('content_id', $content->id)
            ->where('passed', true)
            ->exists();
    }

    /**
     * Batched counterpart to isReady(), for list views (catalog) — one
     * query for the whole page instead of N, same shape as
     * ContentService::getProgressForContents().
     *
     * @param  list<int>  $contentIds
     * @return array<int, bool> content_id => true; absent means not ready
     */
    public function readyMapForContents(int $userId, array $contentIds): array
    {
        if ($contentIds === []) {
            return [];
        }

        return ContentExamAttempt::query()
            ->where('user_id', $userId)
            ->whereIn('content_id', $contentIds)
            ->where('passed', true)
            ->distinct()
            ->pluck('content_id')
            ->mapWithKeys(fn (int $id) => [$id => true])
            ->all();
    }

    /**
     * @return array{steps: array, latest_attempt: ContentExamAttempt|null, ready: bool, pass_threshold_pct: float}
     */
    public function readiness(int $userId, Content $content): array
    {
        return [
            'steps' => $this->stepsStatus($userId, $content),
            'latest_attempt' => $this->latestAttempt($userId, $content),
            'ready' => $this->isReady($userId, $content),
            'pass_threshold_pct' => (float) config('ai.exam.pass_threshold_pct', 80),
        ];
    }
}
