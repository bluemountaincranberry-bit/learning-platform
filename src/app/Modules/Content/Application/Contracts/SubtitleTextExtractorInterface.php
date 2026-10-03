<?php

namespace App\Modules\Content\Application\Contracts;

interface SubtitleTextExtractorInterface
{
    /**
     * Extracts plain dialogue text from a .srt or .vtt subtitle file on disk.
     *
     * @throws \App\Exceptions\SubtitleExtractionException
     */
    public function extractFromPath(string $absolutePath): string;
}
