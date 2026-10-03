<?php

namespace App\Modules\Srs\Application\Contracts;

interface ReviewMistakesReaderInterface
{
    /** @return array{mistake_count: int, mistakes: array<int, array{item: string, grade: int, reviewed_at: ?string}>, note?: string} */
    public function recentForUser(int $userId, int $limit): array;
}
