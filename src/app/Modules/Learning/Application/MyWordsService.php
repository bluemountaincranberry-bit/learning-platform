<?php

namespace App\Modules\Learning\Application;

use App\Modules\Content\Application\Contracts\MyWordsCatalogInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class MyWordsService
{
    public function __construct(
        private readonly MyWordsCatalogInterface $words,
        private readonly LessonLexemeSourceReader $lessonSources,
    ) {}

    /** @param array<string, mixed> $filters */
    public function getPaginated(int $userId, array $filters = []): LengthAwarePaginator
    {
        $paginator = $this->words->getPaginated($userId, $filters);
        $sourcesByLexeme = $this->lessonSources->forLearner(
            $userId,
            $paginator->getCollection()->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        );

        foreach ($paginator->getCollection() as $lexeme) {
            $lexeme->setAttribute('lesson_sources', $sourcesByLexeme->get($lexeme->id, collect())->all());
        }

        return $paginator;
    }
}
