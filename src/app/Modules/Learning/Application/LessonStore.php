<?php

namespace App\Modules\Learning\Application;

use App\Modules\Learning\Application\Contracts\LessonAnalysisStoreInterface;
use App\Modules\Learning\Application\Contracts\LessonNotesWriterInterface;
use App\Modules\Learning\Application\Data\LessonAnalysisContext;
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

    public function startRun(int $runId): bool
    {
        return LessonAnalysisRun::query()->whereKey($runId)
            ->where('status', LessonAnalysisRun::STATUS_PENDING)
            ->update(['status' => LessonAnalysisRun::STATUS_RUNNING, 'started_at' => now()]) === 1;
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

    public function createLexeme(int $runId, array $attributes): void
    {
        $type = $attributes['type'] ?? null;
        $attributes['type'] = is_string($type) && in_array($type, LessonLexemeCandidate::TYPES, true)
            ? $type : LessonLexemeCandidate::TYPE_WORD;
        LessonLexemeCandidate::query()->create([
            ...$attributes, 'lesson_analysis_run_id' => $runId, 'status' => LessonLexemeCandidate::STATUS_PENDING,
        ]);
    }

    public function createGrammar(int $runId, array $attributes): void
    {
        LessonGrammarCandidate::query()->create([
            ...$attributes, 'lesson_analysis_run_id' => $runId, 'status' => LessonGrammarCandidate::STATUS_PENDING,
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
