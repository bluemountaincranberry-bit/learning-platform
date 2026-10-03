<?php

namespace App\Modules\Content\Application\Data;

final readonly class GrammarExplanationSource
{
    /** @param array<int, array{example: string, translation: ?string}> $examples */
    public function __construct(
        public string $title,
        public ?string $level,
        public ?string $summary,
        public ?string $body,
        public array $examples,
    ) {}
}
