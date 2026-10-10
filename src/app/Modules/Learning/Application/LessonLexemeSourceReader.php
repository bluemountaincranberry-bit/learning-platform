<?php

namespace App\Modules\Learning\Application;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Reads lesson provenance for canonical lexemes shown in the learner's word list. */
final class LessonLexemeSourceReader
{
    /**
     * @param  list<int>  $lexemeIds
     * @return Collection<int, Collection<int, array{lesson_id: int, lesson_title: string, candidate_id: int}>>
     */
    public function forLearner(int $userId, array $lexemeIds): Collection
    {
        if ($lexemeIds === []) {
            return collect();
        }

        return DB::table('user_lexeme_sources')
            ->join('lesson_lexeme_candidates', 'lesson_lexeme_candidates.id', '=', 'user_lexeme_sources.lesson_lexeme_candidate_id')
            ->join('lessons', 'lessons.id', '=', 'lesson_lexeme_candidates.lesson_id')
            ->where('user_lexeme_sources.user_id', $userId)
            ->where('lessons.user_id', $userId)
            ->where('user_lexeme_sources.source_kind', 'lesson')
            ->whereIn('user_lexeme_sources.lexeme_id', $lexemeIds)
            ->whereNull('lesson_lexeme_candidates.deleted_at')
            ->orderBy('lessons.lesson_date')
            ->orderBy('lessons.id')
            ->get([
                'user_lexeme_sources.lexeme_id',
                'lesson_lexeme_candidates.id as candidate_id',
                'lessons.id as lesson_id',
                'lessons.title as lesson_title',
            ])
            ->groupBy('lexeme_id')
            ->map(fn (Collection $sources): Collection => $sources
                ->unique('lesson_id')
                ->map(fn (object $source): array => [
                    'lesson_id' => (int) $source->lesson_id,
                    'lesson_title' => $source->lesson_title ?: 'Lesson',
                    'candidate_id' => (int) $source->candidate_id,
                ])
                ->values());
    }
}
