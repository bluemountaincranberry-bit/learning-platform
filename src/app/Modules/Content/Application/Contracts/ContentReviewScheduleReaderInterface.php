<?php

namespace App\Modules\Content\Application\Contracts;

interface ContentReviewScheduleReaderInterface
{
    /** @return list<string> */
    public function itemKeys(int $userId): array;

    /** @return list<int> */
    public function dueContentIds(int $userId): array;
}
