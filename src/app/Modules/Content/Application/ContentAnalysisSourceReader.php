<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\ContentAnalysisSourceReaderInterface;
use App\Modules\Content\Application\Data\ContentAnalysisSource;
use App\Modules\Content\Domain\Models\Content;

final class ContentAnalysisSourceReader implements ContentAnalysisSourceReaderInterface
{
    public function get(int $contentId): ?ContentAnalysisSource
    {
        $content = Content::query()->find($contentId, ['id', 'source_text', 'language', 'level']);

        return $content === null ? null : new ContentAnalysisSource(
            (int) $content->id,
            $content->source_text,
            $content->language,
            $content->level,
        );
    }

    public function hasSourceText(int $contentId): ?bool
    {
        $content = $this->get($contentId);

        return $content === null ? null : trim((string) $content->sourceText) !== '';
    }
}
