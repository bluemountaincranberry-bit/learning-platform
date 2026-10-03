<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LexemeExplanationRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $userId,
        public int $lexemeId,
        public string $source = 'study_screen'
    ) {}
}
