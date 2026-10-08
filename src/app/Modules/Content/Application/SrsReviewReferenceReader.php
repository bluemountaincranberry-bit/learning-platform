<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\SrsReviewReferenceReaderInterface;
use App\Modules\Content\Domain\Models\ContentLexeme;
use Illuminate\Support\Facades\DB;

final class SrsReviewReferenceReader implements SrsReviewReferenceReaderInterface
{
    public function contentOccurrenceBelongsToUser(int $userId, int $contentLexemeId, int $lexemeId): bool
    {
        return DB::table('user_lexeme_sources')
            ->join('content_lexemes', 'content_lexemes.id', '=', 'user_lexeme_sources.content_lexeme_id')
            ->where('user_lexeme_sources.user_id', $userId)
            ->where('user_lexeme_sources.lexeme_id', $lexemeId)
            ->where('user_lexeme_sources.source_kind', 'content')
            ->where('content_lexemes.id', $contentLexemeId)
            ->where('content_lexemes.lexeme_id', $lexemeId)
            ->exists();
    }

    public function transcriptSegmentBelongsToUser(int $userId, int $segmentId, int $lexemeId): bool
    {
        return DB::table('transcript_segment_lexemes')
            ->join('transcript_segments', 'transcript_segments.id', '=', 'transcript_segment_lexemes.transcript_segment_id')
            ->join('content_lexemes', 'content_lexemes.id', '=', 'transcript_segment_lexemes.content_lexeme_id')
            ->join('user_lexeme_sources', 'user_lexeme_sources.content_lexeme_id', '=', 'content_lexemes.id')
            ->where('transcript_segment_lexemes.transcript_segment_id', $segmentId)
            ->whereColumn('transcript_segments.content_id', 'content_lexemes.content_id')
            ->where('user_lexeme_sources.user_id', $userId)
            ->where('user_lexeme_sources.lexeme_id', $lexemeId)
            ->where('user_lexeme_sources.source_kind', 'content')
            ->where('content_lexemes.lexeme_id', $lexemeId)
            ->exists();
    }

    public function contentLexemeIdForCard(int $userId, int $lexemeId, ?int $contentId): ?int
    {
        $query = DB::table('user_lexeme_sources')
            ->join('content_lexemes', 'content_lexemes.id', '=', 'user_lexeme_sources.content_lexeme_id')
            ->where('user_lexeme_sources.user_id', $userId)
            ->where('user_lexeme_sources.lexeme_id', $lexemeId)
            ->where('user_lexeme_sources.source_kind', 'content')
            ->when($contentId !== null, fn ($query) => $query->where('content_lexemes.content_id', $contentId))
            ->orderBy('user_lexeme_sources.id');

        $contentLexemeIds = $query->limit(2)->pluck('user_lexeme_sources.content_lexeme_id');

        return $contentLexemeIds->count() === 1 ? (int) $contentLexemeIds->first() : null;
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
