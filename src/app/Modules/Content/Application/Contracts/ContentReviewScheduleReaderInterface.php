<?php

namespace App\Modules\Content\Application\Contracts;

interface ContentReviewScheduleReaderInterface
{
    /** @return list<int> */
    public function lexemeIds(int $userId): array;

    /** @return list<int> */
    public function dueContentIds(int $userId): array;
}
