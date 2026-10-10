<?php

namespace App\Modules\Learning\Application;

use App\Modules\Learning\Domain\Models\Lesson;
use App\Modules\Learning\Domain\Models\LessonCorrection;
use App\Modules\Learning\Domain\Models\LessonGrammarCandidate;
use App\Modules\Learning\Domain\Models\LessonLexemeCandidate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Owns editable, lesson-scoped words, grammar points, and corrections. */
class LessonItemService
{
    public function createLexeme(Lesson $lesson, array $attributes): LessonLexemeCandidate
    {
        return $lesson->lexemeCandidates()->create([
            ...$attributes,
            'normalized_text' => Str::lower(trim($attributes['text'])),
            'type' => $attributes['type'] ?? LessonLexemeCandidate::TYPE_WORD,
            'status' => LessonLexemeCandidate::STATUS_NEW,
            'source' => 'manual',
        ]);
    }

    public function updateLexeme(Lesson $lesson, int $id, array $attributes): LessonLexemeCandidate
    {
        /** @var LessonLexemeCandidate $item */
        $item = $lesson->lexemeCandidates()->findOrFail($id);
        $this->normalizeLexemeUpdate($item, $attributes);
        $item->update($attributes);

        return $item->refresh();
    }

    public function deleteLexeme(Lesson $lesson, int $id): void
    {
        $lesson->lexemeCandidates()->findOrFail($id)->delete();
    }

    public function permanentlyDeleteLexeme(Lesson $lesson, int $id): void
    {
        DB::transaction(function () use ($lesson, $id): void {
            $candidate = $lesson->lexemeCandidates()->lockForUpdate()->findOrFail($id);
            $this->permanentlyDeleteLexemeCandidates(collect([$candidate]));
        });
    }

    /** @param list<int> $ids @return list<int> */
    public function permanentlyDeleteLexemes(Lesson $lesson, array $ids): array
    {
        return DB::transaction(function () use ($lesson, $ids): array {
            $candidates = $lesson->lexemeCandidates()->whereIn('id', $ids)->lockForUpdate()->get();
            if ($candidates->count() !== count($ids)) {
                abort(404);
            }

            return $this->permanentlyDeleteLexemeCandidates($candidates);
        });
    }

    /** @param Collection<int, LessonLexemeCandidate> $candidates @return list<int> */
    private function permanentlyDeleteLexemeCandidates(Collection $candidates): array
    {
        $candidateIds = $candidates->map(fn (LessonLexemeCandidate $candidate): int => (int) $candidate->getKey())->all();
        DB::table('user_lexeme_sources')->whereIn('lesson_lexeme_candidate_id', $candidateIds)->delete();

        foreach ($candidates as $candidate) {
            $candidate->forceDelete();
        }

        return $candidateIds;
    }

    public function restoreLexeme(Lesson $lesson, int $id): LessonLexemeCandidate
    {
        /** @var LessonLexemeCandidate $item */
        $item = $lesson->lexemeCandidates()->withTrashed()->findOrFail($id);
        $item->restore();

        return $item;
    }

    public function createGrammar(Lesson $lesson, array $attributes): LessonGrammarCandidate
    {
        return $lesson->grammarCandidates()->create([
            ...$attributes,
            'status' => LessonGrammarCandidate::STATUS_NEW,
            'source' => 'manual',
        ]);
    }

    public function updateGrammar(Lesson $lesson, int $id, array $attributes): LessonGrammarCandidate
    {
        /** @var LessonGrammarCandidate $item */
        $item = $lesson->grammarCandidates()->findOrFail($id);
        if (isset($attributes['title']) && $attributes['title'] !== $item->title) {
            $attributes['matched_grammar_rule_id'] = null;
            $attributes['personal_grammar_rule_id'] = null;
            $attributes['match_score'] = null;
            $attributes['status'] = LessonGrammarCandidate::STATUS_NEW;
        }
        $item->update($attributes);

        return $item->refresh();
    }

    public function deleteGrammar(Lesson $lesson, int $id): void
    {
        $lesson->grammarCandidates()->findOrFail($id)->delete();
    }

    public function restoreGrammar(Lesson $lesson, int $id): LessonGrammarCandidate
    {
        /** @var LessonGrammarCandidate $item */
        $item = $lesson->grammarCandidates()->withTrashed()->findOrFail($id);
        $item->restore();

        return $item;
    }

    public function createCorrection(Lesson $lesson, array $attributes): LessonCorrection
    {
        return $lesson->corrections()->create([...$attributes, 'source' => 'manual']);
    }

    public function updateCorrection(Lesson $lesson, int $id, array $attributes): LessonCorrection
    {
        /** @var LessonCorrection $item */
        $item = $lesson->corrections()->findOrFail($id);
        $item->update($attributes);

        return $item->refresh();
    }

    public function deleteCorrection(Lesson $lesson, int $id): void
    {
        $lesson->corrections()->findOrFail($id)->delete();
    }

    public function restoreCorrection(Lesson $lesson, int $id): LessonCorrection
    {
        /** @var LessonCorrection $item */
        $item = $lesson->corrections()->withTrashed()->findOrFail($id);
        $item->restore();

        return $item;
    }

    /** @param array<string, mixed> $attributes */
    private function normalizeLexemeUpdate(LessonLexemeCandidate $item, array &$attributes): void
    {
        if (isset($attributes['text']) && $attributes['text'] !== $item->text) {
            $attributes['normalized_text'] = Str::lower(trim($attributes['text']));
            $attributes['matched_lexeme_id'] = null;
            $attributes['match_score'] = null;
            $attributes['status'] = LessonLexemeCandidate::STATUS_NEW;
        }
    }
}
