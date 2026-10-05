<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\LexemeIdentityReaderInterface;
use App\Modules\Content\Domain\Models\Lexeme;

final class LexemeIdentityReader implements LexemeIdentityReaderInterface
{
    public function lemmasByIds(array $lexemeIds): array
    {
        return Lexeme::query()->whereIn('id', $lexemeIds)->pluck('lemma', 'id')
            ->mapWithKeys(static fn ($lemma, $id): array => [(int) $id => (string) $lemma])
            ->all();
    }
}
