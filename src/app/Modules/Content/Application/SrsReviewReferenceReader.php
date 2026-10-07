<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\SrsReviewReferenceReaderInterface;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\TranscriptSegment;

final class SrsReviewReferenceReader implements SrsReviewReferenceReaderInterface
{
    public function lexemeBelongsToContent(int $lexemeId, int $contentId, ?int $canonicalLexemeId = null): bool
    {
        return ContentLexeme::query()->whereKey($lexemeId)->where('content_id', $contentId)
            ->when($canonicalLexemeId !== null, fn ($query) => $query->where('lexeme_id', $canonicalLexemeId))
            ->exists();
    }

    public function transcriptSegmentBelongsToContent(int $segmentId, int $contentId, ?int $canonicalLexemeId = null): bool
    {
        return TranscriptSegment::query()->whereKey($segmentId)->where('content_id', $contentId)
            ->when($canonicalLexemeId !== null, fn ($query) => $query->whereHas('lexemes', fn ($lexemes) => $lexemes->where('content_lexemes.lexeme_id', $canonicalLexemeId)))
            ->exists();
    }

    public function lexemeIdForItemKey(int $contentId, string $itemKey): ?int
    {
        [$type, $text] = str_contains($itemKey, ':')
            ? explode(':', $itemKey, 2)
            : ['word', $itemKey];

        return ContentLexeme::query()
            ->where('content_id', $contentId)
            ->where('type', $type)
            ->where('text', $text)
            ->value('id');
    }

    public function occurrenceContext(int $contentLexemeId): ?array
    {
        $row = ContentLexeme::query()
            ->join('contents', 'contents.id', '=', 'content_lexemes.content_id')
            ->where('content_lexemes.id', $contentLexemeId)
            ->first([
                'content_lexemes.id',
                'content_lexemes.content_id',
                'contents.language',
                'contents.level',
            ]);

        return $row === null ? null : [
            'id' => (int) $row->id,
            'content_id' => (int) $row->content_id,
            'language' => $row->language,
            'level' => $row->level,
        ];
    }
}
