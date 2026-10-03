<?php

namespace App\Modules\Content\Infrastructure\Integrations;

use App\Contracts\YoutubeTranscriptFetcherInterface;
use App\Exceptions\TranscriptUnavailableException;
use App\Exceptions\YoutubeTranscriptProviderException;
use App\Modules\Content\Application\Transcript\TranscriptDocument;
use App\Modules\Content\Application\Transcript\TranscriptSegmentData;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class YoutubeWebTranscriptFetcher implements YoutubeTranscriptFetcherInterface
{
    public function __construct(
        private readonly string $baseUrl = 'https://www.youtube.com',
        private readonly int $timeout = 15,
        private readonly string $userAgent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/131 Safari/537.36',
    ) {}

    public function fetch(string $sourceUrl, ?string $language = null): TranscriptDocument
    {
        $videoId = $this->extractVideoId(trim($sourceUrl));
        if ($videoId === null) {
            throw new YoutubeTranscriptProviderException('Invalid YouTube URL: cannot extract video ID.');
        }

        $response = Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->timeout($this->timeout)
            ->withHeaders([
                'Accept-Language' => $language ?: 'en',
                'User-Agent' => $this->userAgent,
            ])
            ->get('/watch', array_filter([
                'v' => $videoId,
                'hl' => $language,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''));

        if ($response->failed()) {
            throw new YoutubeTranscriptProviderException(
                'YouTube page request failed: '.$response->reason(),
                $response->status(),
            );
        }

        $playerResponse = $this->extractPlayerResponse($response->body());
        $tracks = $playerResponse['captions']['playerCaptionsTracklistRenderer']['captionTracks'] ?? [];
        if (! is_array($tracks) || $tracks === []) {
            throw new TranscriptUnavailableException('YouTube captions are not available.');
        }

        $track = $this->selectTrack($tracks, $language);
        if (! is_array($track) || ! is_string($track['baseUrl'] ?? null)) {
            throw new TranscriptUnavailableException('YouTube captions are not available in the requested language.');
        }

        return $this->fetchTrack($track['baseUrl'], (string) ($track['languageCode'] ?? $language));
    }

    /** @return array<string, mixed> */
    private function extractPlayerResponse(string $html): array
    {
        $marker = 'ytInitialPlayerResponse';
        $markerPosition = strpos($html, $marker);
        if ($markerPosition === false) {
            throw new YoutubeTranscriptProviderException('YouTube player response was not found.');
        }

        $jsonStart = strpos($html, '{', $markerPosition);
        if ($jsonStart === false) {
            throw new YoutubeTranscriptProviderException('YouTube player response is malformed.');
        }

        $json = $this->extractJsonObject($html, $jsonStart);
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw new YoutubeTranscriptProviderException('YouTube player response is invalid JSON.');
        }

        return $decoded;
    }

    private function extractJsonObject(string $text, int $start): string
    {
        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($text);

        for ($index = $start; $index < $length; $index++) {
            $character = $text[$index];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($character === '"') {
                $inString = true;
            } elseif ($character === '{') {
                $depth++;
            } elseif ($character === '}' && --$depth === 0) {
                return substr($text, $start, $index - $start + 1);
            }
        }

        throw new YoutubeTranscriptProviderException('YouTube player response is incomplete.');
    }

    /** @param array<int, mixed> $tracks */
    private function selectTrack(array $tracks, ?string $language): ?array
    {
        if ($language === null || $language === '') {
            return is_array($tracks[0] ?? null) ? $tracks[0] : null;
        }

        $requested = strtolower($language);
        foreach ($tracks as $track) {
            if (is_array($track) && strtolower((string) ($track['languageCode'] ?? '')) === $requested) {
                return $track;
            }
        }

        foreach ($tracks as $track) {
            if (is_array($track) && str_starts_with(strtolower((string) ($track['languageCode'] ?? '')), $requested.'-')) {
                return $track;
            }
        }

        return null;
    }

    private function fetchTrack(string $baseUrl, ?string $language): TranscriptDocument
    {
        $trackUrl = $baseUrl.(str_contains($baseUrl, '?') ? '&' : '?').'fmt=json3';
        $response = Http::timeout($this->timeout)
            ->withHeaders(['User-Agent' => $this->userAgent])
            ->get($trackUrl);

        if ($response->failed()) {
            throw new YoutubeTranscriptProviderException(
                'YouTube caption request failed: '.$response->reason(),
                $response->status(),
            );
        }

        $document = $this->fromJsonTrack($response, $language);
        if ($document !== null) {
            return $document;
        }

        return $this->fromXmlTrack($response->body(), $language);
    }

    private function fromJsonTrack(Response $response, ?string $language): ?TranscriptDocument
    {
        $events = $response->json('events');
        if (! is_array($events)) {
            return null;
        }

        $segments = [];
        foreach ($events as $index => $event) {
            if (! is_array($event) || ! is_array($event['segs'] ?? null)) {
                continue;
            }

            $text = trim(implode('', array_map(
                static fn (array $segment): string => (string) ($segment['utf8'] ?? ''),
                array_filter($event['segs'], 'is_array'),
            )));
            if ($text === '') {
                continue;
            }

            $startMs = (int) ($event['tStartMs'] ?? 0);
            $endMs = $startMs + (int) ($event['dDurationMs'] ?? 0);
            $segments[] = new TranscriptSegmentData($startMs, $endMs, $text, (string) $index);
        }

        return $segments === [] ? null : $this->documentFromSegments($segments, $language);
    }

    private function fromXmlTrack(string $body, ?string $language): TranscriptDocument
    {
        $xml = @simplexml_load_string($body);
        if ($xml === false) {
            throw new YoutubeTranscriptProviderException('YouTube caption response is not valid JSON or XML.');
        }

        $segments = [];
        foreach ($xml->text as $index => $node) {
            $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode((string) $node, ENT_QUOTES | ENT_XML1, 'UTF-8')) ?? '');
            if ($text === '') {
                continue;
            }

            $startMs = (int) round(((float) ($node['start'] ?? 0)) * 1000);
            $endMs = $startMs + (int) round(((float) ($node['dur'] ?? 0)) * 1000);
            $segments[] = new TranscriptSegmentData($startMs, $endMs, $text, (string) $index);
        }

        if ($segments === []) {
            throw new TranscriptUnavailableException('YouTube captions are empty.');
        }

        return $this->documentFromSegments($segments, $language);
    }

    /** @param array<int, TranscriptSegmentData> $segments */
    private function documentFromSegments(array $segments, ?string $language): TranscriptDocument
    {
        return new TranscriptDocument(
            fullText: implode(' ', array_map(static fn (TranscriptSegmentData $segment): string => $segment->text, $segments)),
            segments: $segments,
            language: $language,
            source: 'youtube-web',
        );
    }

    private function extractVideoId(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $id = null;

        if ($host === 'youtu.be') {
            $id = explode('/', $path)[0] ?? null;
        } elseif ($path === 'watch') {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $id = $query['v'] ?? null;
        } elseif (preg_match('#^(?:embed|shorts|live)/([^/]+)#', $path, $matches)) {
            $id = $matches[1];
        }

        return is_string($id) && preg_match('/^[a-zA-Z0-9_-]{11}$/', $id) === 1 ? $id : null;
    }
}
