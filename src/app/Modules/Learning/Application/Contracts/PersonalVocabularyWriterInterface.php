<?php

namespace App\Modules\Learning\Application\Contracts;

interface PersonalVocabularyWriterInterface
{
    /** @return array{id:int,lemma:string,language:string,is_personal:bool} */
    public function addConfirmedWord(int $userId, string $language, string $lemma): array;
}
