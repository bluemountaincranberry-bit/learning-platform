<?php

namespace App\Contracts;

use App\Modules\Content\Application\Transcript\TranscriptDocument;

interface YoutubeTranscriptFetcherInterface
{
    /**
     * Fetch transcript text and timing data for a YouTube video URL.
     *
     * @param  string|null  $language  Preferred transcript language (e.g. content's language code).
     *
     * @throws \RuntimeException when transcript cannot be fetched
     */
    public function fetch(string $sourceUrl, ?string $language = null): TranscriptDocument;
}
