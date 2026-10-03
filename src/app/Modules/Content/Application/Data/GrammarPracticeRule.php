<?php

namespace App\Modules\Content\Application\Data;

final readonly class GrammarPracticeRule
{
    public function __construct(
        public int $id,
        public string $title,
    ) {}
}
