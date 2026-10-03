<?php

namespace App\Modules\Content\Application\Transcript;

final readonly class TranscriptSegmentData
{
    public function __construct(
        public int $startMs,
        public int $endMs,
        public string $text,
        public ?string $sourceKey = null,
    ) {}
}
