<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\ContentTitleReaderInterface;
use App\Modules\Content\Domain\Models\Content;

final class ContentTitleReader implements ContentTitleReaderInterface
{
    public function titlesByIds(array $contentIds): array
    {
        return Content::query()
            ->whereIn('id', $contentIds)
            ->pluck('title', 'id')
            ->mapWithKeys(fn (string $title, int|string $id): array => [(int) $id => $title])
            ->all();
    }
}
