<?php

namespace App\Modules\Content\Infrastructure\Integrations;

use App\Contracts\YoutubeTranscriptFetcherInterface;
use App\Exceptions\YoutubeTranscriptProviderException;
use App\Modules\Content\Application\Transcript\TranscriptDocument;
use App\Modules\Content\Application\Transcript\TranscriptSegmentData;
use Illuminate\Support\Facades\Http;

class LibraryYoutubeTranscriptFetcher implements YoutubeTranscriptFetcherInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $endpoint = '/transcript',
        private readonly int $timeout = 45,
    ) {}

    public function fetch(string $sourceUrl, ?string $language = null): TranscriptDocument
    {
        $response = Http::timeout($this->timeout)->post(rtrim($this->baseUrl, '/').'/'.ltrim($this->endpoint, '/'), [
            'url' => $sourceUrl,
            'language' => $language,
        ]);

        if ($response->failed()) {
            throw new YoutubeTranscriptProviderException(
                (string) ($response->json('error') ?: 'YouTube transcript library worker failed.'),
                $response->status(),
            );
        }

        $segments = collect($response->json('segments', []))
            ->filter(fn (mixed $segment): bool => is_array($segment) && trim((string) ($segment['text'] ?? '')) !== '')
            ->map(fn (array $segment): TranscriptSegmentData => new TranscriptSegmentData(
                (int) ($segment['startMs'] ?? 0),
                (int) ($segment['endMs'] ?? 0),
                trim((string) $segment['text']),
                isset($segment['sourceKey']) ? (string) $segment['sourceKey'] : null,
            ))
            ->values()
            ->all();

        if ($segments === []) {
            throw new YoutubeTranscriptProviderException('YouTube transcript library returned no captions.');
        }

        return new TranscriptDocument(
            fullText: implode(' ', array_map(static fn (TranscriptSegmentData $segment): string => $segment->text, $segments)),
            segments: $segments,
            language: $response->json('language') ?: $language,
            source: (string) ($response->json('source') ?: 'youtube-transcript-library'),
        );
    }
}
