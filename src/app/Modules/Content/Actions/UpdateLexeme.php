<?php

namespace App\Modules\Content\Actions;

use App\Modules\Content\Application\Contracts\GrammarCatalogServiceInterface;
use App\Modules\Content\Domain\Models\Lexeme;

final class UpdateLexeme
{
    public function __construct(private readonly GrammarCatalogServiceInterface $catalogService) {}

    public function execute(Lexeme $lexeme, array $lexemeData): Lexeme
    {
        return $this->catalogService->updateLexeme($lexeme, $lexemeData);
    }
}
