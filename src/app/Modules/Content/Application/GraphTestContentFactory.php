<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\GraphTestContentFactoryInterface;
use App\Modules\Content\Domain\Models\Content;

final class GraphTestContentFactory implements GraphTestContentFactoryInterface
{
    public function create(string $title, string $language, string $transcript): int
    {
        return Content::query()->create([
            'type' => 'youtube',
            'title' => $title,
            'language' => $language,
            'origin' => 'curated',
            'status' => 'ready',
            'source_text' => $transcript,
        ])->id;
    }
}
