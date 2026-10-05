<?php

namespace App\Modules\Content\Application\Data;

final readonly class ExerciseContentContext
{
    public function __construct(
        public int $contentId,
        public string $language,
        public ?string $level,
        public ?int $contentLexemeId,
        public ?int $canonicalLexemeId,
        public ?string $lexemeType,
        public ?string $lexemeText,
        public ?int $transcriptSegmentId,
    ) {}
}
