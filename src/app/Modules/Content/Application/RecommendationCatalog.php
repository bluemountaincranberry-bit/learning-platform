<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\RecommendationCatalogInterface;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use Illuminate\Support\Collection;

final class RecommendationCatalog implements RecommendationCatalogInterface
{
    public function contentCandidates(int $userId, ?string $language, int $limit): Collection
    {
        return Content::query()->where('status', 'ready')
            ->whereExists(function ($query) use ($userId): void {
                $query->selectRaw('1')->from('content_lexemes')->whereColumn('content_lexemes.content_id', 'contents.id')
                    ->whereNotExists(function ($progressQuery) use ($userId): void {
                        $progressQuery->selectRaw('1')->from('user_lexeme_progress')
                            ->whereColumn('user_lexeme_progress.lexeme_id', 'content_lexemes.lexeme_id')
                            ->where('user_lexeme_progress.user_id', $userId);
                    });
            })
            ->when($language !== null && $language !== '', fn ($query) => $query->where('language', $language))
            ->limit($limit)->get();
    }

    public function lexemeCandidates(int $userId, ?string $language, int $limit): Collection
    {
        return ContentLexeme::query()->whereHas('content', fn ($query) => $query->where('status', 'ready'))
            ->whereNotExists(function ($query) use ($userId): void {
                $query->selectRaw('1')->from('user_lexeme_progress')
                    ->whereColumn('user_lexeme_progress.lexeme_id', 'content_lexemes.lexeme_id')
                    ->where('user_lexeme_progress.user_id', $userId);
            })
            ->when($language !== null && $language !== '', fn ($query) => $query->whereHas('content', fn ($contentQuery) => $contentQuery->where('language', $language)))
            ->limit($limit)->get();
    }
}
