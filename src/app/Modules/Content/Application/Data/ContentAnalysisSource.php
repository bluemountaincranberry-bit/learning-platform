<?php

namespace App\Modules\Content\Application\Data;

final readonly class ContentAnalysisSource
{
    public function __construct(
        public int $id,
        public ?string $sourceText,
        public ?string $language,
        public ?string $level,
    ) {}
}
