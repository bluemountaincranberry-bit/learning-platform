<?php

namespace App\Modules\Content\Application\Contracts;

interface ContentLearnerStateReaderInterface
{
    /**
     * @param  list<int>  $contentLexemeIds
     * @return array{learned: list<int>, skipped: list<int>, needs_context_review: list<int>, confidence: array<int, array<string, int>>}
     */
    public function lexemeState(int $userId, array $contentLexemeIds): array;

    /**
     * @param  list<int>  $contentIds
     * @return array<int, int>
     */
    public function learnedCountsByContent(int $userId, array $contentIds): array;
}
