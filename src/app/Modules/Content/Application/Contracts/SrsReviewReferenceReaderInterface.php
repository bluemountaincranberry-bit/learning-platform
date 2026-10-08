<?php

namespace App\Modules\Content\Application\Contracts;

interface SrsReviewReferenceReaderInterface
{
    public function contentOccurrenceBelongsToUser(int $userId, int $contentLexemeId, int $lexemeId): bool;

    public function transcriptSegmentBelongsToUser(int $userId, int $segmentId, int $lexemeId): bool;

    public function contentLexemeIdForCard(int $userId, int $lexemeId, ?int $contentId): ?int;

    /** @return array{id: int, content_id: int, language: ?string, level: ?string}|null */
    public function occurrenceContext(int $contentLexemeId): ?array;
}
