<?php

namespace App\Modules\Content\Application\Contracts;

use App\Modules\Content\Domain\Models\ContentLexeme;

interface LexemeServiceInterface
{
    public function markLearned(ContentLexeme $lexeme, int $userId): void;

    public function unmarkLearned(ContentLexeme $lexeme, int $userId): void;

    public function startLearning(ContentLexeme $lexeme, int $userId): void;

    public function stopLearning(ContentLexeme $lexeme, int $userId): void;

    public function skip(ContentLexeme $lexeme, int $userId): void;

    public function unskip(ContentLexeme $lexeme, int $userId): void;
}
