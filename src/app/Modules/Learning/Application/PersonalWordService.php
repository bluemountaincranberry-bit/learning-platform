<?php

namespace App\Modules\Learning\Application;

use App\Modules\Content\Application\Contracts\PersonalLexemeResolverInterface;
use App\Modules\Learning\Domain\Models\Lesson;
use App\Modules\Learning\Domain\Models\LessonLexemeCandidate;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use App\Modules\Learning\Domain\Models\UserLexemeSource;
use App\Modules\Srs\Application\Contracts\ExerciseReviewSchedulerInterface;
use App\Modules\Srs\Application\Contracts\SrsRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class PersonalWordService
{
    public function __construct(
        private readonly PersonalLexemeResolverInterface $lexemes,
        private readonly SrsRepositoryInterface $cards,
        private readonly LexemeConfidenceService $confidence,
        private readonly ExerciseReviewSchedulerInterface $reviewScheduler,
    ) {}

    /** @return array{id:int,lemma:string,language:string,is_personal:bool} */
    public function add(int $userId, string $language, string $lemma): array
    {
        return DB::transaction(function () use ($userId, $language, $lemma): array {
            $lexeme = $this->lexemes->resolveOrCreate($userId, $language, $lemma);

            UserLexemeSource::query()->firstOrCreate(
                ['user_id' => $userId, 'lexeme_id' => $lexeme['id'], 'source_kind' => 'manual'],
                [
                    'content_lexeme_id' => null,
                    'lesson_lexeme_candidate_id' => null,
                    'source_text' => $lemma,
                    'source_example' => null,
                    'display_label_snapshot' => 'My words',
                ],
            );

            $this->cards->firstOrCreateCard(
                ['user_id' => $userId, 'lexeme_id' => $lexeme['id']],
                [
                    'content_id' => null,
                    'item_key' => null,
                    'state' => 'new',
                    'interval_days' => 1,
                    'ease_factor' => 2.5,
                    'next_review_at' => now(),
                    'deactivated_at' => null,
                ],
            );

            return $lexeme;
        });
    }

    /** @return array{id:int,lemma:string,language:string,is_personal:bool,in_review:bool} */
    public function addLessonCandidate(int $userId, Lesson $lesson, int $candidateId): array
    {
        return DB::transaction(function () use ($userId, $lesson, $candidateId): array {
            /** @var LessonLexemeCandidate $candidate */
            $candidate = $lesson->lexemeCandidates()->lockForUpdate()->findOrFail($candidateId);
            $lexeme = $this->lexemes->resolveOrCreate($userId, (string) $lesson->language, (string) $candidate->text);
            $this->startLearning($userId, $lexeme['id']);

            UserLexemeSource::query()->updateOrCreate(
                ['user_id' => $userId, 'lesson_lexeme_candidate_id' => $candidate->id],
                [
                    'lexeme_id' => $lexeme['id'],
                    'source_kind' => 'lesson',
                    'content_lexeme_id' => null,
                    'source_text' => $candidate->text,
                    'source_example' => $candidate->example,
                    'display_label_snapshot' => (string) ($lesson->title ?: 'Lesson'),
                ],
            );

            $candidate->forceFill([
                'matched_lexeme_id' => $lexeme['id'],
                'status' => LessonLexemeCandidate::STATUS_MATCHED,
            ])->save();

            return [...$lexeme, 'in_review' => true];
        });
    }

    public function startLearning(int $userId, int $lexemeId): void
    {
        $lexemeId = $this->lexemes->visibleLexemeId($userId, $lexemeId);

        $card = $this->cards->firstOrCreateCard(
            ['user_id' => $userId, 'lexeme_id' => $lexemeId],
            [
                'content_id' => null,
                'item_key' => null,
                'state' => 'new',
                'interval_days' => 1,
                'ease_factor' => 2.5,
                'next_review_at' => now(),
                'deactivated_at' => null,
            ],
        );

        if ($card->deactivated_at !== null) {
            $card->update(['deactivated_at' => null]);
        }
    }

    public function stopLearning(int $userId, int $lexemeId): bool
    {
        $this->lexemes->visibleLexemeId($userId, $lexemeId);

        return $this->cards->deactivateCardsForLearning($userId, $lexemeId) > 0;
    }

    public function markKnown(int $userId, int $lexemeId): void
    {
        $this->lexemes->visibleLexemeId($userId, $lexemeId);

        $progress = UserLexemeProgress::query()->firstOrNew(['user_id' => $userId, 'lexeme_id' => $lexemeId]);
        if (! $progress->exists) {
            $progress->content_lexeme_id = null;
        }
        $progress->learned_at = now();
        $progress->save();
    }

    public function unmarkKnown(int $userId, int $lexemeId): void
    {
        $this->lexemes->visibleLexemeId($userId, $lexemeId);
        UserLexemeProgress::query()->where('user_id', $userId)->where('lexeme_id', $lexemeId)->delete();
    }

    public function practice(int $userId, int $lexemeId, string $dimension, bool $correct, bool $hintUsed): bool
    {
        $lexemeId = $this->lexemes->visibleLexemeId($userId, $lexemeId);

        return DB::transaction(function () use ($userId, $lexemeId, $dimension, $correct, $hintUsed): bool {
            $this->confidence->recordCanonicalOutcome($lexemeId, $userId, $dimension, $correct, $hintUsed);

            return $this->reviewScheduler->scheduleReview($userId, $lexemeId, $correct ? 3 : 1, [
                'exercise_type' => $dimension === 'recall' ? 'self_check' : $dimension,
                'error_type' => $correct ? null : 'unknown_meaning',
                'hint_used' => $hintUsed,
            ]);
        });
    }
}
