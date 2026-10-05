<?php

namespace App\Modules\Content\Application\Contracts;

interface PersonalLexemeResolverInterface
{
    /** @return array{id:int,lemma:string,language:string,is_personal:bool} */
    public function resolveOrCreate(int $ownerUserId, string $language, string $lemma): array;

    public function visibleLexemeId(int $userId, int $lexemeId): int;
}
