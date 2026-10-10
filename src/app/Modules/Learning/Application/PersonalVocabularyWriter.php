<?php

namespace App\Modules\Learning\Application;

use App\Modules\Learning\Application\Contracts\PersonalVocabularyWriterInterface;

final class PersonalVocabularyWriter implements PersonalVocabularyWriterInterface
{
    public function __construct(private readonly PersonalWordService $words) {}

    public function addConfirmedWord(int $userId, string $language, string $lemma): array
    {
        return $this->words->add($userId, $language, $lemma);
    }
}
