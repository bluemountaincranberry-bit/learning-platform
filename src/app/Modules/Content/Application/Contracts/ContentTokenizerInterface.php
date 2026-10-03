<?php

namespace App\Modules\Content\Application\Contracts;

interface ContentTokenizerInterface
{
    /** @return array<int, string> */
    public function tokenize(string $text): array;
}
