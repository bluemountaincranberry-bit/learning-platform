<?php

namespace App\Modules\Learning\Application;

use App\Modules\Content\Application\Contracts\SelfCheckLexemeCatalogInterface;
use App\Modules\Learning\Domain\Models\ExerciseAttempt;
use App\Modules\Learning\Domain\Models\SelfCheckSubmission;
use App\Modules\Learning\Domain\Models\UserLexemeConfidence;
use App\Modules\Learning\Domain\Models\UserLexemeContextCheck;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use App\Modules\Srs\Application\Contracts\ExerciseReviewSchedulerInterface;
use App\Modules\User\Application\Contracts\LearningFlowLearnerReaderInterface;
use Illuminate\Support\Facades\DB;

class SelfCheckService
{
    private const GRADE_CORRECT = 3;

    private const GRADE_INCORRECT = 1;

    public function __construct(
        private SelfCheckLexemeCatalogInterface $lexemeCatalog,
        private ExerciseReviewSchedulerInterface $reviewScheduler,
        private LearningFlowLearnerReaderInterface $learnerReader,
        private LexemeConfidenceService $confidenceService,
        private AdaptiveActivitySelector $activitySelector,
        private LearningRetryService $retryService,
        private LearningFlowMetricsService $metrics,
    ) {}

    /** @return list<array<string, mixed>> */
    public function getItemsForStart(int $contentId, int $userId, int $limit = 10): array
    {
        $learnedIds = UserLexemeProgress::query()->where('user_id', $userId)
            ->whereNotNull('content_lexeme_id')->pluck('content_lexeme_id')
            ->map(fn ($id): int => (int) $id)->all();
        $learner = $this->learnerReader->forUser($userId);
        $translationLanguage = (string) ($learner['translation_language'] ?? config('ai.analysis.translation_language', 'ru'));
        $items = collect($this->lexemeCatalog->forContent($contentId, $learnedIds !== [] ? $learnedIds : null, $translationLanguage));
        $dueRetries = $this->retryService->due($userId, $contentId, $limit);
        $retryPositions = $dueRetries->pluck('content_lexeme_id')->flip();
        $retryRecords = $dueRetries->keyBy('content_lexeme_id');
        $items = $items->sortBy(fn (array $item): int => $retryPositions->get($item['content_lexeme_id'], PHP_INT_MAX))->values();

        $ids = $items->pluck('content_lexeme_id');
        $confidences = UserLexemeConfidence::query()->where('user_id', $userId)->whereIn('content_lexeme_id', $ids)->get()->keyBy('content_lexeme_id');
        $recentAttempts = ExerciseAttempt::query()->where('user_id', $userId)->whereIn('content_lexeme_id', $ids)
            ->where('created_at', '>=', now()->subDays(30))->latest('created_at')
            ->get(['content_lexeme_id', 'error_type'])->groupBy('content_lexeme_id');
        $flow = app(LearningFlowResolver::class)->resolveForUserId($userId, $this->lexemeCatalog->language($contentId));

        return $items->shuffle()->take($limit)->map(function (array $item) use ($confidences, $recentAttempts, $flow, $userId, $retryRecords): array {
            $contentLexemeId = (int) $item['content_lexeme_id'];
            $confidence = $confidences->get($contentLexemeId);
            $attempts = $recentAttempts->get($contentLexemeId, collect());
            $recommendation = config('learning.adaptive.enabled', true)
                ? $this->activitySelector->choose($item['level'], $flow['config'], [
                    'confidence' => $confidence?->only(['recognition', 'recall', 'production', 'listening', 'speaking']),
                    'recent_error' => $attempts->first()?->error_type,
                    'attempts' => $attempts->count(),
                    'has_example' => $item['example'] !== null || $item['examples'] !== [],
                    'has_translation' => $item['translation'] !== null,
                ])
                : ['activity' => 'quick-check', 'target_dimension' => 'recall', 'stage' => 'recall', 'difficulty' => 50, 'reason' => 'Legacy practice fallback'];
            $this->metrics->recordSelection($userId, $flow['profile']?->id, $contentLexemeId, $recommendation);

            return [
                ...$item,
                'adaptive_activity' => $recommendation['activity'],
                'target_dimension' => $recommendation['target_dimension'],
                'learning_stage' => $recommendation['stage'],
                'difficulty' => $recommendation['difficulty'],
                'selection_reason' => $recommendation['reason'],
                'retry_id' => $retryRecords->get($contentLexemeId)?->id,
            ];
        })->values()->all();
    }

    /** @param array<int, array{content_lexeme_id: int, known: bool}> $answers */
    public function computeScore(int $contentId, int $userId, array $answers, ?string $operationId = null): array
    {
        return DB::transaction(function () use ($contentId, $userId, $answers, $operationId): array {
            if ($operationId !== null) {
                $existing = SelfCheckSubmission::query()->where('user_id', $userId)->where('operation_id', $operationId)->lockForUpdate()->first();
                if ($existing !== null) {
                    return [...$existing->result, 'idempotent' => true];
                }
            }
            $result = $this->computeScoreOnce($contentId, $userId, $answers);
            if ($operationId !== null) {
                SelfCheckSubmission::query()->create(['user_id' => $userId, 'content_id' => $contentId, 'operation_id' => $operationId, 'result' => $result]);
            }

            return [...$result, 'idempotent' => false];
        });
    }

    /** @param array<int, array<string, mixed>> $answers */
    private function computeScoreOnce(int $contentId, int $userId, array $answers): array
    {
        $ids = array_values(array_filter(array_map(fn (array $answer): ?int => isset($answer['content_lexeme_id']) ? (int) $answer['content_lexeme_id'] : null, $answers)));
        $learner = $this->learnerReader->forUser($userId);
        $lexemesById = $this->lexemeCatalog->forContent($contentId, $ids, (string) ($learner['translation_language'] ?? 'ru'));
        $flow = app(LearningFlowResolver::class)->resolveForUserId($userId, $this->lexemeCatalog->language($contentId));
        $total = 0;
        $correct = 0;

        foreach ($answers as $answer) {
            $contentLexemeId = isset($answer['content_lexeme_id']) ? (int) $answer['content_lexeme_id'] : 0;
            $lexeme = $lexemesById[$contentLexemeId] ?? null;
            if ($lexeme === null) {
                continue;
            }
            $total++;
            $known = ! empty($answer['known']);
            $correct += $known ? 1 : 0;
            $this->applyToSrsCard($lexeme, $userId, $known, $answer);
            $this->recordContextCheck($lexeme, $userId, $known);
            if (isset($answer['exercise_type'])) {
                $this->confidenceService->recordOutcome($contentLexemeId, $userId, $this->dimensionFor($answer['exercise_type']), $known, (bool) ($answer['hint_used'] ?? false));
            } else {
                $this->confidenceService->record($contentLexemeId, $userId, [
                    'recognition' => $known ? 75 : 20, 'recall' => $known ? 75 : 15, 'listening' => $known ? 75 : 20,
                ]);
            }
            $retryId = isset($answer['retry_id']) ? (int) $answer['retry_id'] : null;
            if ($retryId !== null) {
                $this->retryService->resolve($retryId, $userId, $known);
            } elseif (! $known) {
                $this->retryService->schedule($userId, $contentLexemeId, $contentId, (string) ($answer['exercise_type'] ?? 'self_check'), $answer['error_type'] ?? 'unknown_meaning');
            }
            $this->metrics->recordOutcome($userId, $flow['profile']?->id, $contentLexemeId, (string) ($answer['exercise_type'] ?? 'self_check'), $known, $this->dimensionFor((string) ($answer['exercise_type'] ?? 'self_check')));
        }

        return ['total' => $total, 'correct' => $correct, 'score_pct' => $total > 0 ? round(100.0 * $correct / $total, 1) : 0.0];
    }

    private function dimensionFor(string $exerciseType): string
    {
        return match ($exerciseType) {
            'listening', 'dictation' => 'listening',
            'cloze', 'production' => 'production',
            'speaking', 'shadowing' => 'speaking',
            'listen-recognize' => 'recognition',
            default => 'recall',
        };
    }

    /** @param array<string, mixed> $lexeme @param array<string, mixed> $answer */
    private function applyToSrsCard(array $lexeme, int $userId, bool $known, array $answer): void
    {
        $this->reviewScheduler->scheduleReview($userId, (int) $lexeme['content_id'], (string) $lexeme['item_key'], $known ? self::GRADE_CORRECT : self::GRADE_INCORRECT, [
            'content_lexeme_id' => (int) $lexeme['content_lexeme_id'],
            'transcript_segment_id' => $answer['transcript_segment_id'] ?? null,
            'exercise_type' => $answer['exercise_type'] ?? 'self_check',
            'error_type' => $answer['error_type'] ?? ($known ? null : 'unknown_meaning'),
            'hint_used' => $answer['hint_used'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $lexeme */
    private function recordContextCheck(array $lexeme, int $userId, bool $known): void
    {
        if ($lexeme['canonical_lexeme_id'] === null) {
            return;
        }
        UserLexemeContextCheck::query()->updateOrCreate(
            ['user_id' => $userId, 'lexeme_id' => $lexeme['canonical_lexeme_id']],
            ['last_result' => $known ? UserLexemeContextCheck::RESULT_CORRECT : UserLexemeContextCheck::RESULT_NEEDS_WORK, 'checked_at' => now()],
        );
    }
}
