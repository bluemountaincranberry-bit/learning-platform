<?php

namespace App\Modules\Content\Application\Transcript;

use App\Modules\Content\Application\Contracts\TranscriptLexemeLinkerInterface;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\TranscriptSegment;
use Illuminate\Support\Facades\DB;

class TranscriptSegmentStore implements TranscriptLexemeLinkerInterface
{
    public function linkForContent(int $contentId): void
    {
        $this->linkLexemes(Content::query()->findOrFail($contentId));
    }

    public function replace(Content $content, TranscriptDocument $document): void
    {
        DB::transaction(function () use ($content, $document): void {
            $content->transcriptSegments()->delete();

            foreach ($document->segments as $sequence => $segment) {
                $content->transcriptSegments()->create([
                    'sequence' => $sequence,
                    'start_ms' => $segment->startMs,
                    'end_ms' => $segment->endMs > 0 ? $segment->endMs : null,
                    'text' => $segment->text,
                    'language' => $document->language,
                    'source' => $document->source,
                    'source_key' => $segment->sourceKey,
                ]);
            }
        });
    }

    /**
     * Link the unique content lexemes to every matching transcript occurrence.
     */
    public function linkLexemes(Content $content): void
    {
        $lexemes = $content->lexemes()->get();
        $byText = $lexemes->groupBy(fn (ContentLexeme $lexeme): string => $this->normalize($lexeme->text));
        $phrases = $lexemes
            ->where('type', ContentLexeme::TYPE_PHRASE)
            ->sortByDesc(fn (ContentLexeme $lexeme): int => mb_strlen($lexeme->text));

        DB::transaction(function () use ($content, $byText, $phrases): void {
            $content->transcriptSegments()->each(function (TranscriptSegment $segment) use ($byText, $phrases): void {
                $tokens = $this->tokenOccurrences($segment->text);
                $links = [];

                foreach ($tokens as $token) {
                    $matches = $byText->get($this->normalize($token['text']), collect());
                    foreach ($matches as $lexeme) {
                        $links[] = [
                            'content_lexeme_id' => $lexeme->id,
                            'start_offset' => $token['start'],
                            'end_offset' => $token['end'],
                            'surface_text' => $token['text'],
                            'match_type' => 'exact',
                            'confidence' => 1,
                        ];
                    }
                }

                foreach ($phrases as $phrase) {
                    foreach ($this->phraseOccurrences($segment->text, $phrase->text) as $occurrence) {
                        $links[] = [
                            'content_lexeme_id' => $phrase->id,
                            'start_offset' => $occurrence['start'],
                            'end_offset' => $occurrence['end'],
                            'surface_text' => $occurrence['text'],
                            'match_type' => 'phrase',
                            'confidence' => 1,
                        ];
                    }
                }

                DB::table('transcript_segment_lexemes')->where('transcript_segment_id', $segment->id)->delete();
                if ($links !== []) {
                    $now = now();
                    DB::table('transcript_segment_lexemes')->insert(array_map(
                        static fn (array $link): array => $link + [
                            'transcript_segment_id' => $segment->id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                        $links,
                    ));
                }
            });
        });
    }

    /** @return array<int, array{text: string, start: int, end: int}> */
    private function tokenOccurrences(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]+(?:[\'’][\p{L}\p{N}]+)*/u', $text, $matches, PREG_OFFSET_CAPTURE);

        return array_map(static function (array $match) use ($text): array {
            $surface = $match[0];
            $start = mb_strlen(substr($text, 0, $match[1]), 'UTF-8');

            return [
                'text' => $surface,
                'start' => $start,
                'end' => $start + mb_strlen($surface, 'UTF-8'),
            ];
        }, $matches[0] ?? []);
    }

    private function normalize(string $text): string
    {
        return mb_strtolower(str_replace('’', "'", trim($text)));
    }

    /** @return array<int, array{text: string, start: int, end: int}> */
    private function phraseOccurrences(string $text, string $phrase): array
    {
        $pattern = '/(?<![\p{L}\p{N}])'.preg_quote(str_replace('’', "'", $phrase), '/').'(?!(?:[\p{L}\p{N}]))/iu';
        preg_match_all($pattern, str_replace('’', "'", $text), $matches, PREG_OFFSET_CAPTURE);

        return array_map(static function (array $match) use ($text): array {
            $start = mb_strlen(substr($text, 0, $match[1]), 'UTF-8');
            $surface = $match[0];

            return [
                'text' => $surface,
                'start' => $start,
                'end' => $start + mb_strlen($surface, 'UTF-8'),
            ];
        }, $matches[0] ?? []);
    }
}
