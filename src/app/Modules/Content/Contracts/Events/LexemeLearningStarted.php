<?php

namespace App\Modules\Content\Contracts\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LexemeLearningStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $userId,
        public int $lexemeId,
        public int $contentLexemeId,
        public string $itemKey,
        public ?int $contentId,
        public string $sourceText,
        public string $displayLabelSnapshot,
    ) {}
}
