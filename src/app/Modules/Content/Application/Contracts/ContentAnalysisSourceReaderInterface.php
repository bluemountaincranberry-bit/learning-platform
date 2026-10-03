<?php

namespace App\Modules\Content\Application\Contracts;

use App\Modules\Content\Application\Data\ContentAnalysisSource;

interface ContentAnalysisSourceReaderInterface
{
    public function get(int $contentId): ?ContentAnalysisSource;

    /** Null means the content does not exist. */
    public function hasSourceText(int $contentId): ?bool;
}
