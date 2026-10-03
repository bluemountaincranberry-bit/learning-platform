<?php

namespace App\Modules\Content\Application\Contracts;

interface ContentTitleReaderInterface
{
    /** @param list<int> $contentIds
     * @return array<int, string>
     */
    public function titlesByIds(array $contentIds): array;
}
