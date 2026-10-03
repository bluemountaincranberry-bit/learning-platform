<?php

namespace App\Modules\Learning\Application;

use App\Modules\Content\Application\Contracts\ExerciseContentGatewayInterface;
use App\Modules\Content\Application\Data\ExerciseContentContext;
use App\Modules\Learning\Application\Contracts\PronunciationAssessmentProviderInterface;
use App\Modules\Learning\Application\Contracts\SpeechToTextProviderInterface;
use App\Modules\Learning\Domain\Models\ExerciseAttempt;
use App\Modules\Learning\Infrastructure\AudioNormalizationService;
use App\Modules\Learning\Interfaces\Jobs\ProcessExerciseAttemptJob;
use App\Modules\Srs\Application\Contracts\ExerciseReviewSchedulerInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ExerciseAttemptService
{
    public function __construct(
        private readonly ExerciseComparisonService $comparison,
        private readonly SpeechToTextProviderInterface $speechToText,
        private readonly PronunciationAssessmentProviderInterface $pronunciation,
        private readonly LexemeConfidenceService $confidence,
        private readonly ExerciseContentGatewayInterface $exerciseContent,
        private readonly ExerciseReviewSchedulerInterface $reviewScheduler,
        private readonly AudioNormalizationService $audioNormalization,
        private readonly PointsAwardService $points,
        private readonly LearningRetryService $retries,
        private readonly LearningFlowMetricsService $metrics,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(Authenticatable $learner, array $data, ?UploadedFile $audio = null): ExerciseAttempt
    {
        $contentContext = $this->exerciseContent->resolveForCreation(
            $learner,
            (int) $data['content_id'],
            isset($data['content_lexeme_id']) ? (int) $data['content_lexeme_id'] : null,
            isset($data['transcript_segment_id']) ? (int) $data['transcript_segment_id'] : null,
        );

        $audioPath = $audio !== null ? $audio->store('exercise-attempts', 'local') : null;
        $attempt = ExerciseAttempt::query()->create([
            'user_id' => (int) $learner->getAuthIdentifier(),
            'content_id' => $contentContext->contentId,
            'content_lexeme_id' => $contentContext->contentLexemeId,
            'transcript_segment_id' => $contentContext->transcriptSegmentId,
            'exercise_type' => $data['exercise_type'],
            'status' => 'pending', 'target_text' => $data['target_text'], 'user_text' => $data['user_text'] ?? null,
            'hint_used' => (bool) ($data['hint_used'] ?? false), 'replay_count' => (int) ($data['replay_count'] ?? 0),
            'audio_disk' => $audioPath === null ? null : 'local', 'audio_path' => $audioPath,
            'audio_expires_at' => $audioPath === null ? null : now()->addHour(),
        ]);

        ProcessExerciseAttemptJob::dispatch($attempt->id);

        return $attempt->fresh();
    }

    public function process(int $attemptId): ExerciseAttempt
    {
        $attempt = ExerciseAttempt::query()->findOrFail($attemptId);
        if ($attempt->status === 'completed') {
            return $attempt;
        }
        $attempt->update(['status' => 'processing']);
        $contentContext = $this->exerciseContent->contextForAttempt(
            (int) $attempt->content_id,
            $attempt->content_lexeme_id !== null ? (int) $attempt->content_lexeme_id : null,
            $attempt->transcript_segment_id !== null ? (int) $attempt->transcript_segment_id : null,
        );

        $audioPath = $attempt->audio_path !== null ? Storage::disk($attempt->audio_disk ?: 'local')->path($attempt->audio_path) : null;
        try {
            $userText = trim((string) ($attempt->user_text ?? ''));
            $providerResult = [];
            if ($audioPath !== null) {
                $transcribed = $this->speechToText->transcribe($audioPath, $contentContext->language);
                $userText = trim($transcribed['text']);
                $providerResult['transcription'] = $transcribed;
            }
            $comparison = $this->comparison->compare($attempt->target_text, $userText);
            if ($attempt->exercise_type !== 'dictation' && $audioPath !== null) {
                $normalized = ['path' => $audioPath, 'temporary' => false];
                try {
                    $normalized = $this->audioNormalization->forPronunciation($audioPath);
                    $providerResult['pronunciation'] = $this->pronunciation->assess($normalized['path'], $attempt->target_text, $contentContext->language);
                } catch (\Throwable $exception) {
                    $providerResult['pronunciation_error'] = $exception->getMessage();
                } finally {
                    $this->audioNormalization->cleanup($normalized['path'], $normalized['temporary']);
                }
                $providerScore = $providerResult['pronunciation']['accuracy'] ?? null;
                if (is_numeric($providerScore)) {
                    $comparison['score'] = (int) round(($comparison['score'] + (int) $providerScore) / 2);
                }
            }
            $attempt->update([
                'status' => 'completed', 'user_text' => $userText, 'score' => $comparison['score'],
                'is_correct' => $comparison['correct'], 'error_type' => $comparison['error_type'], 'provider_result' => $providerResult,
            ]);
            if ($contentContext->contentLexemeId !== null) {
                $this->recordLearningOutcome((int) $attempt->user_id, $contentContext, $attempt, $comparison['score']);
            }
            if ($attempt->audio_path !== null) {
                Storage::disk($attempt->audio_disk ?: 'local')->delete($attempt->audio_path);
            }
        } catch (\Throwable $exception) {
            $attempt->update(['status' => 'failed', 'failure_reason' => $exception->getMessage()]);
            throw $exception;
        }

        return $attempt->fresh();
    }

    private function recordLearningOutcome(int $userId, ExerciseContentContext $contentContext, ExerciseAttempt $attempt, int $score): void
    {
        $dimension = match ($attempt->exercise_type) {
            'shadowing', 'speaking' => 'speaking', 'dictation' => 'listening', default => 'recall'
        };
        $contentLexemeId = (int) $contentContext->contentLexemeId;
        $this->confidence->recordOutcome($contentLexemeId, $userId, $dimension, $score >= 90, (bool) $attempt->hint_used);
        $reviewScheduled = $this->reviewScheduler->scheduleReview(
            $userId,
            $contentContext->contentId,
            "{$contentContext->lexemeType}:{$contentContext->lexemeText}",
            $score >= 90 ? 3 : 1,
            [
                'content_lexeme_id' => $contentLexemeId,
                'transcript_segment_id' => $attempt->transcript_segment_id,
                'exercise_type' => $attempt->exercise_type,
                'error_type' => $attempt->error_type,
                'hint_used' => $attempt->hint_used,
            ],
        );
        if (! $reviewScheduled) {
            $this->points->awardForAttempt(
                $attempt,
                $userId,
                $contentLexemeId,
                $contentContext->language,
                $contentContext->level,
            );
        }
        if ($score < 90 && ! $reviewScheduled) {
            $this->retries->scheduleForExercise($attempt, $userId, $contentLexemeId, $contentContext->contentId);
        }
        $flow = app(LearningFlowResolver::class)->resolveForUserId($userId, $contentContext->language);
        $this->metrics->recordOutcome($userId, $flow['profile']?->id, $contentLexemeId, $attempt->exercise_type, $score >= 90, $dimension, (int) ($attempt->score ?? 0), ['score' => $score, 'hint_used' => (bool) $attempt->hint_used]);
    }
}
