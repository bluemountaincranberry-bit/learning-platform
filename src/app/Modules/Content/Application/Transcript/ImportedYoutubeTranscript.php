<?php

namespace App\Modules\Content\Application\Transcript;

final readonly class ImportedYoutubeTranscript
{
    /** @param array<int, array{start_ms: int, end_ms?: int|null, text: string}> $segments */
    public function __construct(
        public string $fullText,
        public array $segments,
        public string $language,
    ) {}

    public function toDocument(): TranscriptDocument
    {
        return new TranscriptDocument(
            fullText: $this->fullText,
            segments: array_map(
                static fn (array $segment): TranscriptSegmentData => new TranscriptSegmentData(
                    (int) $segment['start_ms'],
                    (int) ($segment['end_ms'] ?? 0),
                    trim($segment['text']),
                ),
                $this->segments,
            ),
            language: $this->language,
            source: 'youtube-extension',
        );
    }
}
