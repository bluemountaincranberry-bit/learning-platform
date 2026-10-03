<?php

namespace App\Modules\Content\Application\Contracts;

use App\Modules\Content\Application\Data\ContentLearnerContext;
use App\Modules\Content\Domain\Models\Content;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ContentServiceInterface
{
    /**
     * @param  array{type?: string, language?: string, level?: string}  $filters
     */
    public function getReadyPaginated(array $filters = []): LengthAwarePaginator;

    /**
     * @param  array{type?: string, language?: string, level?: string}  $filters
     */
    public function getReadyPaginatedWithProgress(?ContentLearnerContext $learner, array $filters = []): LengthAwarePaginator;

    public function getLexemesWithLearnedFlags(Content $content, ?ContentLearnerContext $learner): Collection;

    /**
     * @return \Illuminate\Support\Collection<int, Content>
     */
    public function getMySubmissions(int $userId): Collection;

    /**
     * @param  array{title: string, source_url: string, language: string, level?: string|null}  $data
     */
    public function submitYoutube(array $data, int $userId): Content;

    public function importYoutubeTranscript(array $data, int $userId): Content;

    /**
     * @param  \Illuminate\Support\Collection<int, Content>  $contents
     * @return array<int, array{learned_count: int, in_learning_count: int, total_lexemes: int, progress_pct: float}>
     */
    public function getProgressForContents(Collection $contents, ?ContentLearnerContext $learner): array;

    /**
     * @return array{learned_count: int, in_learning_count: int, total_lexemes: int, progress_pct: float}
     */
    public function getProgressForContent(Content $content, ?ContentLearnerContext $learner): array;
}
