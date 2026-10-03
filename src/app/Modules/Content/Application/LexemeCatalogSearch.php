<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\LexemeCatalogSearchInterface;
use App\Modules\Content\Domain\Models\Lexeme;

final class LexemeCatalogSearch implements LexemeCatalogSearchInterface
{
    public function searchPublished(string $query, ?string $language, int $limit): array
    {
        return Lexeme::query()
            ->where('status', Lexeme::STATUS_PUBLISHED)
            ->where('lemma', 'like', '%'.$query.'%')
            ->when($language !== null && $language !== '', fn ($q) => $q->where('language', $language))
            ->orderBy('lemma')
            ->limit($limit)
            ->get(['lemma', 'language', 'level', 'part_of_speech'])
            ->map(fn (Lexeme $lexeme): array => [
                'lemma' => $lexeme->lemma,
                'language' => $lexeme->language,
                'level' => $lexeme->level,
                'part_of_speech' => $lexeme->part_of_speech,
            ])->all();
    }

    public function search(string $language, string $query, int $limit): array
    {
        return Lexeme::query()
            ->when($language !== '', fn ($q) => $q->where('language', $language))
            ->when($query !== '', fn ($q) => $q->whereRaw(
                'LOWER(lemma) LIKE ?',
                ['%'.addcslashes(mb_strtolower($query), '%_\\').'%']
            ))
            ->limit($limit)
            ->get(['id', 'lemma', 'language', 'level', 'status'])
            ->map(fn (Lexeme $lexeme): array => [
                'id' => $lexeme->id,
                'lemma' => $lexeme->lemma,
                'language' => $lexeme->language,
                'level' => $lexeme->level,
                'status' => $lexeme->status,
            ])->all();
    }
}
