<?php

namespace App\Modules\Content\Application\Transcript;

final readonly class TranscriptDocument
{
    /**
     * @param  array<int, TranscriptSegmentData>  $segments
     */
    public function __construct(
        public string $fullText,
        public array $segments,
        public ?string $language,
        public string $source,
    ) {}

    public static function fromPlainText(string $text, ?string $language, string $source): self
    {
        $text = trim($text);

        return new self(
            fullText: $text,
            segments: $text === '' ? [] : [new TranscriptSegmentData(0, 0, $text)],
            language: $language,
            source: $source,
        );
    }
}
