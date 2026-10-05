<?php

namespace App\Modules\Content\Contracts\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LexemeLearningStopped
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $userId,
        public int $lexemeId,
        public int $contentLexemeId,
        public string $itemKey,
    ) {}
}
