<?php

namespace App\Modules\Content\Actions;

use App\Modules\Content\Domain\Models\Lexeme;

final class DeleteLexeme
{
    public function execute(Lexeme $lexeme): void
    {
        $lexeme->delete();
    }
}
