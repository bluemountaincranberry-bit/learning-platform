<?php

namespace App\Modules\Learning\Application;

use App\Modules\Learning\Application\Contracts\StudentVocabularyReaderInterface;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use Illuminate\Database\Eloquent\Builder;

class StudentVocabularyReader implements StudentVocabularyReaderInterface
{
    public function summary(int $userId, ?string $language): array
    {
        $query = $this->query($userId, $language);

        return [
            'total' => (clone $query)->count(),
            'levels' => (clone $query)
                ->whereNotNull('lexemes.level')
                ->selectRaw('lexemes.level as level, count(*) as learned_count')
                ->groupBy('lexemes.level')
                ->pluck('learned_count', 'level')
                ->map(fn ($count): int => (int) $count)
                ->all(),
        ];
    }

    public function history(int $userId, ?string $language, int $limit): array
    {
        return $this->query($userId, $language)
            ->orderByDesc('user_lexeme_progress.learned_at')
            ->limit($limit)
            ->select('lexemes.lemma', 'lexemes.language', 'lexemes.level', 'user_lexeme_progress.learned_at')
            ->get()
            ->map(fn (UserLexemeProgress $row): array => [
                'lemma' => $row->lemma,
                'language' => $row->language,
                'level' => $row->level,
                'learned_at' => $row->learned_at?->toIso8601String(),
            ])
            ->all();
    }

    private function query(int $userId, ?string $language): Builder
    {
        return UserLexemeProgress::query()
            ->where('user_lexeme_progress.user_id', $userId)
            ->join('lexemes', 'lexemes.id', '=', 'user_lexeme_progress.lexeme_id')
            ->when(
                $language !== null && $language !== '',
                fn (Builder $query) => $query->where('lexemes.language', $language)
            );
    }
}
