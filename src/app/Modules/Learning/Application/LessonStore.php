<?php

namespace App\Modules\Learning\Application;

use App\Contracts\Ai\LessonAnalysisContext;
use App\Contracts\Ai\LessonAnalysisStoreInterface;
use App\Contracts\Ai\LessonNotesWriterInterface;
use App\Modules\Learning\Domain\Models\Lesson;
use App\Modules\Learning\Domain\Models\LessonAnalysisRun;
use App\Modules\Learning\Domain\Models\LessonGrammarCandidate;
use App\Modules\Learning\Domain\Models\LessonLexemeCandidate;
use Illuminate\Support\Facades\DB;

class LessonStore implements LessonAnalysisStoreInterface, LessonNotesWriterInterface
{
    public function getRun(int $runId): ?LessonAnalysisContext
    {
        $run = LessonAnalysisRun::query()->with('lesson')->find($runId);
        if ($run === null) {
            return null;
        }

        return new LessonAnalysisContext(
            $run->id, $run->lesson_id, $run->lesson->user_id,
            (string) $run->lesson->source_text, $run->lesson->language,
        );
    }

    public function startRun(int $runId, bool $retry = false): bool
    {
        return LessonAnalysisRun::query()->whereKey($runId)
            ->whereIn('status', $retry
                ? [LessonAnalysisRun::STATUS_PENDING, LessonAnalysisRun::STATUS_FAILED]
                : [LessonAnalysisRun::STATUS_PENDING])
            ->update(['status' => LessonAnalysisRun::STATUS_RUNNING, 'started_at' => now(), 'completed_at' => null, 'failure_reason' => null]) === 1;
    }

    public function completeRun(int $runId): void
    {
        LessonAnalysisRun::query()->whereKey($runId)->update([
            'status' => LessonAnalysisRun::STATUS_COMPLETED, 'completed_at' => now(),
        ]);
    }

    public function failRun(int $runId, string $reason): void
    {
        LessonAnalysisRun::query()->whereKey($runId)->update([
            'status' => LessonAnalysisRun::STATUS_FAILED, 'completed_at' => now(), 'failure_reason' => $reason,
        ]);
    }

    public function persistCandidates(int $runId, array $lexemes, array $grammar): void
    {
        DB::transaction(function () use ($runId, $lexemes, $grammar): void {
            $run = LessonAnalysisRun::query()->lockForUpdate()->findOrFail($runId);
            foreach ($lexemes as $attributes) {
                if (! $run->lexemeCandidates()->where('normalized_text', $attributes['normalized_text'])->exists()) {
                    $this->persistLexemeCandidate($runId, $run->lesson_id, $attributes);
                }
            }
            foreach ($grammar as $attributes) {
                if (! $run->grammarCandidates()->whereRaw('LOWER(title) = ?', [mb_strtolower($attributes['title'])])->exists()) {
                    $this->persistGrammarCandidate($runId, $run->lesson_id, $attributes);
                }
            }
        });
    }

    public function createLexemeCandidate(int $runId, array $attributes): void
    {
        $run = LessonAnalysisRun::query()->findOrFail($runId);
        $this->persistLexemeCandidate($runId, $run->lesson_id, $attributes);
    }

    /** @param array<string, mixed> $attributes */
    private function persistLexemeCandidate(int $runId, int $lessonId, array $attributes): void
    {
        $type = $attributes['type'] ?? null;
        $attributes['type'] = is_string($type) && in_array($type, LessonLexemeCandidate::TYPES, true)
            ? $type : LessonLexemeCandidate::TYPE_WORD;
        LessonLexemeCandidate::query()->create([
            ...$attributes, 'lesson_analysis_run_id' => $runId, 'lesson_id' => $lessonId,
            'status' => LessonLexemeCandidate::STATUS_PENDING, 'source' => 'ai',
        ]);
    }

    public function createGrammarCandidate(int $runId, array $attributes): void
    {
        $run = LessonAnalysisRun::query()->findOrFail($runId);
        $this->persistGrammarCandidate($runId, $run->lesson_id, $attributes);
    }

    /** @param array<string, mixed> $attributes */
    private function persistGrammarCandidate(int $runId, int $lessonId, array $attributes): void
    {
        LessonGrammarCandidate::query()->create([
            ...$attributes, 'lesson_analysis_run_id' => $runId, 'lesson_id' => $lessonId,
            'status' => LessonGrammarCandidate::STATUS_PENDING, 'source' => 'ai',
        ]);
    }

    public function lexemeCandidates(int $runId): array
    {
        return LessonLexemeCandidate::query()->where('lesson_analysis_run_id', $runId)
            ->get(['id', 'normalized_text', 'text'])->toArray();
    }

    public function grammarCandidates(int $runId): array
    {
        return LessonGrammarCandidate::query()->where('lesson_analysis_run_id', $runId)
            ->get(['id', 'title', 'summary'])->toArray();
    }

    public function updateLexemeMatch(int $candidateId, ?int $lexemeId, ?float $score): void
    {
        LessonLexemeCandidate::query()->whereKey($candidateId)->update([
            'matched_lexeme_id' => $lexemeId, 'match_score' => $score,
            'status' => $lexemeId !== null ? LessonLexemeCandidate::STATUS_MATCHED : LessonLexemeCandidate::STATUS_NEW,
        ]);
    }

    public function updateGrammarMatch(int $candidateId, ?int $ruleId, ?float $score): void
    {
        LessonGrammarCandidate::query()->whereKey($candidateId)->update([
            'matched_grammar_rule_id' => $ruleId, 'match_score' => $score,
            'status' => $ruleId !== null ? LessonGrammarCandidate::STATUS_LINKED : LessonGrammarCandidate::STATUS_NEW,
        ]);
    }

    public function appendNotes(int $lessonId, string $text): void
    {
        DB::transaction(function () use ($lessonId, $text): void {
            $lesson = Lesson::query()->lockForUpdate()->find($lessonId);
            if ($lesson !== null) {
                $lesson->update(['source_text' => trim(((string) $lesson->source_text)."\n\n".$text)]);
            }
        });
    }
}
