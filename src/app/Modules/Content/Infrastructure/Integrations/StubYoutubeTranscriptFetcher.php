<?php

namespace App\Modules\Content\Infrastructure\Integrations;

use App\Contracts\YoutubeTranscriptFetcherInterface;
use App\Modules\Content\Application\Transcript\TranscriptDocument;

/**
 * Stub implementation for local fallback and isolated tests.
 * Production uses the configured driver from config/transcripts.php.
 */
class StubYoutubeTranscriptFetcher implements YoutubeTranscriptFetcherInterface
{
    public function fetch(string $sourceUrl, ?string $language = null): TranscriptDocument
    {
        $videoId = $this->extractVideoId($sourceUrl);
        if ($videoId === null) {
            throw new \RuntimeException('Invalid YouTube URL: cannot extract video ID');
        }

        return TranscriptDocument::fromPlainText(
            "Stub transcript for video {$videoId}. Replace with real API in production.",
            $language,
            'youtube-stub',
        );
    }

    private function extractVideoId(string $url): ?string
    {
        $url = trim($url);
        if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)([a-zA-Z0-9_-]{11})#', $url, $m)) {
            return $m[1];
        }

        return null;
    }
}
