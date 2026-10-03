<?php

namespace App\Modules\Srs\Application\Data;

final readonly class ReviewOutcome
{
    public function __construct(
        public int $reviewId,
        public int $userId,
        public string $itemKey,
        public ?int $contentLexemeId,
        public int $grade,
        public bool $isFailing,
        public ?string $exerciseType,
        public ?string $errorType,
        public ?bool $hintUsed,
    ) {}
}
