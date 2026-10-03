<?php

namespace App\Modules\Content\Infrastructure\Integrations;

use App\Contracts\YoutubeTranscriptFetcherInterface;
use App\Modules\Content\Application\Transcript\TranscriptDocument;

class FallbackYoutubeTranscriptFetcher implements YoutubeTranscriptFetcherInterface
{
    /** @param array<int, YoutubeTranscriptFetcherInterface> $fetchers */
    public function __construct(private readonly array $fetchers) {}

    public function fetch(string $sourceUrl, ?string $language = null): TranscriptDocument
    {
        $lastError = null;

        foreach ($this->fetchers as $fetcher) {
            try {
                return $fetcher->fetch($sourceUrl, $language);
            } catch (\Throwable $error) {
                $lastError = $error;
            }
        }

        throw $lastError ?? new \RuntimeException('No YouTube transcript providers are configured.');
    }
}
