<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\DraftContentCreatorInterface;
use App\Modules\Content\Domain\Models\Content;

final class DraftContentCreator implements DraftContentCreatorInterface
{
    public function types(): array
    {
        return Content::TYPES;
    }

    public function levels(): array
    {
        return Content::CEFR_LEVELS;
    }

    public function create(array $data): array
    {
        $content = Content::query()->create($data + ['origin' => 'ai-chat', 'status' => 'draft']);

        return ['id' => $content->id, 'title' => $content->title, 'status' => $content->status];
    }
}
