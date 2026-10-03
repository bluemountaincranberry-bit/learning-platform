<?php

namespace App\Modules\Content\Application\Contracts;

interface SrsReviewReferenceReaderInterface
{
    public function lexemeBelongsToContent(int $lexemeId, int $contentId): bool;

    public function transcriptSegmentBelongsToContent(int $segmentId, int $contentId): bool;

    public function lexemeIdForItemKey(int $contentId, string $itemKey): ?int;

    /** @return array{id: int, content_id: int, language: ?string, level: ?string}|null */
    public function occurrenceContext(int $contentLexemeId): ?array;
}
