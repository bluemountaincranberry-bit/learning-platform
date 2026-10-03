<?php

namespace App\Modules\Content\Infrastructure\Integrations;

use App\Contracts\YoutubeTranscriptFetcherInterface;
use App\Exceptions\YoutubeTranscriptProviderException;
use App\Modules\Content\Application\Transcript\TranscriptDocument;
use App\Modules\Content\Application\Transcript\TranscriptSegmentData;
use Illuminate\Support\Facades\Http;

class PlaywrightYoutubeTranscriptFetcher implements YoutubeTranscriptFetcherInterface
{
    public function __construct(
        private readonly string $baseUrl = 'http://browser:3000',
        private readonly int $timeout = 60,
    ) {}

    public function fetch(string $sourceUrl, ?string $language = null): TranscriptDocument
    {
        $response = Http::timeout($this->timeout)
            ->post(rtrim($this->baseUrl, '/').'/transcript', [
                'url' => $sourceUrl,
                'language' => $language,
            ]);

        if ($response->failed()) {
            throw new YoutubeTranscriptProviderException(
                (string) ($response->json('error') ?: 'Playwright transcript extraction failed.'),
                $response->status(),
            );
        }

        $segments = $response->json('segments');
        if (! is_array($segments) || $segments === []) {
            throw new YoutubeTranscriptProviderException('Playwright returned no YouTube captions.');
        }

        $items = [];
        foreach ($segments as $segment) {
            if (! is_array($segment) || trim((string) ($segment['text'] ?? '')) === '') {
                continue;
            }

            $items[] = new TranscriptSegmentData(
                (int) ($segment['startMs'] ?? 0),
                (int) ($segment['endMs'] ?? 0),
                trim((string) $segment['text']),
                isset($segment['sourceKey']) ? (string) $segment['sourceKey'] : null,
            );
        }

        if ($items === []) {
            throw new YoutubeTranscriptProviderException('Playwright returned empty YouTube captions.');
        }

        return new TranscriptDocument(
            fullText: implode(' ', array_map(static fn (TranscriptSegmentData $segment): string => $segment->text, $items)),
            segments: $items,
            language: $response->json('language') ?: $language,
            source: 'youtube-playwright',
        );
    }
}
