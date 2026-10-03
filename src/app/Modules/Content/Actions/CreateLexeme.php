<?php

namespace App\Modules\Content\Actions;

use App\Modules\Content\Application\Contracts\GrammarCatalogServiceInterface;
use App\Modules\Content\Domain\Models\Lexeme;

final class CreateLexeme
{
    public function __construct(private readonly GrammarCatalogServiceInterface $catalogService) {}

    public function execute(array $lexemeData): Lexeme
    {
        return $this->catalogService->createLexeme($lexemeData);
    }
}
