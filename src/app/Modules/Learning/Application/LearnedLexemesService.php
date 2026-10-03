<?php

namespace App\Modules\Learning\Application;

use App\Modules\Content\Application\Contracts\LearnedLexemeCatalogInterface;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use App\Modules\Srs\Application\Contracts\SrsServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LearnedLexemesService
{
    public function __construct(
        private SrsServiceInterface $srsService,
        private LearnedLexemeCatalogInterface $catalog,
    ) {}

    /**
     * @param  array{language?: string, content_id?: int, date_from?: string, date_to?: string, per_page?: int, page?: int}  $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<\App\Modules\Learning\Domain\Models\UserLexemeProgress>
     */
    public function getPaginated(int $userId, string $translationLanguage, array $filters = []): LengthAwarePaginator
    {
        $learnedLexemeIds = UserLexemeProgress::query()
            ->where('user_id', $userId)
            ->whereNotNull('lexeme_id')
            ->pluck('lexeme_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $visibleLexemeIds = $this->catalog->filterPublicLexemeIds($learnedLexemeIds, $filters);

        $query = UserLexemeProgress::query()
            ->where('user_id', $userId)
            ->whereIn('lexeme_id', $visibleLexemeIds);
        if (! empty($filters['date_from'])) {
            $query->whereDate('learned_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('learned_at', '<=', $filters['date_to']);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);

        $paginator = $query->orderByDesc('learned_at')->paginate($perPage);

        $inReviewItemKeys = $this->srsService->getInReviewItemKeys($userId);
        $entries = $paginator->getCollection()->map(fn (UserLexemeProgress $progress): array => [
            'progress_id' => (int) $progress->id,
            'lexeme_id' => (int) $progress->lexeme_id,
            'content_lexeme_id' => $progress->content_lexeme_id === null ? null : (int) $progress->content_lexeme_id,
        ])->all();
        $presentations = $this->catalog->presentations($entries, $translationLanguage);
        foreach ($paginator->getCollection() as $progress) {
            $presentation = $presentations[$progress->id] ?? [];
            foreach ($presentation as $key => $value) {
                $progress->setAttribute($key, $value);
            }
            $progress->in_review = isset($presentation['item_key'])
                && $inReviewItemKeys->has($presentation['item_key']);
        }

        return $paginator;
    }
}
