<?php

namespace App\Contracts\Ai;

interface LexemeEnrichmentDispatcher
{
    public function dispatchFor(int $lexemeId): void;
}
