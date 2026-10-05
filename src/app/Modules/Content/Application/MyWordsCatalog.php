<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\MyWordsCatalogInterface;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\Lexeme;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class MyWordsCatalog implements MyWordsCatalogInterface
{
    /**
     * @param  array{status?: string, language?: string, level?: string, content_id?: int, search?: string, per_page?: int, page?: int}  $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<\App\Modules\Content\Domain\Models\Lexeme>
     */
    public function getPaginated(int $userId, array $filters = []): LengthAwarePaginator
    {
        $reviewExists = $this->reviewExistsSql($userId);
        $knownExists = $this->knownExistsSql($userId);
        $skippedExists = $this->skippedExistsSql($userId);

        $query = Lexeme::query()
            ->with([
                'examples',
                'translations',
                'associations.relatedLexeme',
                'contentLinks' => fn ($q) => $q
                    ->whereHas('content', fn (Builder $contentQuery) => $contentQuery->whereIn('status', Content::PUBLIC_STATUSES))
                    ->with('content'),
            ])
            ->where(function (Builder $scope) use ($userId): void {
                $scope->where('owner_user_id', $userId)
                    ->orWhere(function (Builder $shared): void {
                        $shared->whereNull('owner_user_id')
                            ->whereHas('contentLinks.content', fn (Builder $q) => $q->whereIn('status', Content::PUBLIC_STATUSES));
                    });
            })
            ->whereRaw("not exists ({$skippedExists})");

        if (! empty($filters['language'])) {
            $query->where('language', $filters['language']);
        }

        if (! empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        if (! empty($filters['content_id'])) {
            $contentId = (int) $filters['content_id'];
            $query->whereHas('contentLinks', fn (Builder $q) => $q
                ->where('content_id', $contentId)
                ->whereHas('content', fn (Builder $contentQuery) => $contentQuery->whereIn('status', Content::PUBLIC_STATUSES)));
        }

        if (! empty($filters['search'])) {
            $search = '%'.strtolower((string) $filters['search']).'%';
            $query->where(function (Builder $q) use ($search): void {
                $q->whereRaw('LOWER(lemma) LIKE ?', [$search])
                    ->orWhereHas('translations', fn (Builder $translationQuery) => $translationQuery->whereRaw('LOWER(translation) LIKE ?', [$search]));
            });
        }

        match ($filters['status'] ?? 'all') {
            'in_learning' => $query->whereRaw("exists ({$reviewExists})"),
            'known' => $query->whereRaw("exists ({$knownExists})")->whereRaw("not exists ({$reviewExists})"),
            'new' => $query->whereRaw("not exists ({$knownExists})")->whereRaw("not exists ({$reviewExists})"),
            default => null,
        };

        $query
            ->orderByRaw("case when exists ({$reviewExists}) then 0 when exists ({$knownExists}) then 1 else 2 end")
            ->orderBy('lemma');

        $perPage = (int) ($filters['per_page'] ?? 15);
        $paginator = $query->paginate($perPage);

        $this->attachUserWordState($paginator, $userId, $filters);

        return $paginator;
    }

    /**
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator<\App\Modules\Content\Domain\Models\Lexeme>  $paginator
     * @param  array{content_id?: int}  $filters
     */
    private function attachUserWordState(LengthAwarePaginator $paginator, int $userId, array $filters): void
    {
        $items = $paginator->getCollection();
        $lexemeIds = $items->pluck('id');

        $progressByLexemeId = DB::table('user_lexeme_progress')
            ->where('user_id', $userId)
            ->whereIn('lexeme_id', $lexemeIds)
            ->get(['lexeme_id', 'content_lexeme_id', 'learned_at'])
            ->keyBy('lexeme_id');

        $reviewRows = $this->reviewRowsForLexemes($userId, $lexemeIds->all());
        $reviewByLexemeId = $reviewRows->keyBy('lexeme_id');

        foreach ($items as $lexeme) {
            $progress = $progressByLexemeId->get($lexeme->id);
            $review = $reviewByLexemeId->get($lexeme->id);
            $preferredContentId = isset($filters['content_id']) ? (int) $filters['content_id'] : null;

            $primaryContext = $lexeme->contentLinks->firstWhere('content_id', $preferredContentId)
                ?? ($review !== null ? $lexeme->contentLinks->firstWhere('id', (int) $review->content_lexeme_id) : null)
                ?? ($progress !== null ? $lexeme->contentLinks->firstWhere('id', (int) $progress->content_lexeme_id) : null)
                ?? $lexeme->contentLinks->first();

            $lexeme->learned_at = $progress?->learned_at !== null ? Carbon::parse($progress->learned_at) : null;
            $lexeme->in_review = $review !== null;
            $lexeme->word_status = $review !== null ? 'in_learning' : ($progress !== null ? 'known' : 'new');
            $lexeme->primary_content_lexeme_id = $primaryContext?->id;
            $lexeme->primary_content_id = $primaryContext?->content_id;
        }
    }

    /**
     * @param  array<int, int>  $lexemeIds
     */
    private function reviewRowsForLexemes(int $userId, array $lexemeIds): \Illuminate\Support\Collection
    {
        if ($lexemeIds === []) {
            return collect();
        }

        $canonical = DB::table('user_lexeme_sources')
            ->join('content_lexemes', 'content_lexemes.id', '=', 'user_lexeme_sources.content_lexeme_id')
            ->where('user_lexeme_sources.user_id', $userId)
            ->whereIn('user_lexeme_sources.lexeme_id', $lexemeIds)
            ->select('user_lexeme_sources.lexeme_id', 'user_lexeme_sources.content_lexeme_id');

        $itemKey = $this->contentLexemeItemKeySql();
        $legacy = DB::table('content_lexemes')
            ->join('srs_cards', function ($join) use ($itemKey): void {
                $join->on('srs_cards.content_id', '=', 'content_lexemes.content_id')
                    ->whereRaw("{$itemKey} = srs_cards.item_key");
            })
            ->where('srs_cards.user_id', $userId)
            ->whereIn('content_lexemes.lexeme_id', $lexemeIds)
            ->select('content_lexemes.lexeme_id', 'content_lexemes.id as content_lexeme_id')
            ->get();

        return $canonical->get()->concat($legacy)->unique('lexeme_id')->values();
    }

    private function reviewExistsSql(int $userId): string
    {
        $itemKey = $this->contentLexemeItemKeySql();

        return "select 1 from srs_cards where srs_cards.lexeme_id = lexemes.id and srs_cards.user_id = {$userId} and srs_cards.deactivated_at is null union all select 1 from content_lexemes join srs_cards on srs_cards.content_id = content_lexemes.content_id and {$itemKey} = srs_cards.item_key where content_lexemes.lexeme_id = lexemes.id and srs_cards.user_id = {$userId} and srs_cards.deactivated_at is null";
    }

    private function knownExistsSql(int $userId): string
    {
        return "select 1 from user_lexeme_progress where user_lexeme_progress.lexeme_id = lexemes.id and user_lexeme_progress.user_id = {$userId}";
    }

    private function skippedExistsSql(int $userId): string
    {
        return "select 1 from user_lexeme_skips where user_lexeme_skips.lexeme_id = lexemes.id and user_lexeme_skips.user_id = {$userId}";
    }

    private function contentLexemeItemKeySql(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "(content_lexemes.type || ':' || content_lexemes.text)"
            : "CONCAT(content_lexemes.type, ':', content_lexemes.text)";
    }
}
