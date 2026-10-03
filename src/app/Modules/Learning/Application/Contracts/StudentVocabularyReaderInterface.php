<?php

namespace App\Modules\Learning\Application\Contracts;

interface StudentVocabularyReaderInterface
{
    /** @return array{total: int, levels: array<string, int>} */
    public function summary(int $userId, ?string $language): array;

    /** @return list<array{lemma: string, language: string, level: ?string, learned_at: ?string}> */
    public function history(int $userId, ?string $language, int $limit): array;
}
