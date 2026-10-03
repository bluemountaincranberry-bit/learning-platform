<?php

namespace App\Modules\Content\Infrastructure\Integrations;

use App\Contracts\YoutubeTranscriptFetcherInterface;
use App\Exceptions\InvalidYoutubeUrlException;
use App\Exceptions\TranscriptUnavailableException;
use App\Exceptions\YoutubeTranscriptProviderException;
use App\Modules\Content\Application\Transcript\TranscriptDocument;
use App\Modules\Content\Application\Transcript\TranscriptSegmentData;
use Illuminate\Support\Facades\Http;

class SupadataYoutubeTranscriptFetcher implements YoutubeTranscriptFetcherInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $mode = 'native',
        private readonly int $timeout = 30,
        private readonly int $pollIntervalMs = 1000,
        private readonly int $maxPolls = 10,
        private readonly ?string $preferredLanguage = null,
    ) {}

    public function fetch(string $sourceUrl, ?string $language = null): TranscriptDocument
    {
        $normalizedUrl = trim($sourceUrl);
        $this->assertValidYoutubeUrl($normalizedUrl);

        if ($this->apiKey === '') {
            throw new YoutubeTranscriptProviderException('Supadata API key is not configured.');
        }

        $response = Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->timeout($this->timeout)
            ->acceptJson()
            ->withHeaders([
                'x-api-key' => $this->apiKey,
            ])
            ->get('/transcript', array_filter([
                'url' => $normalizedUrl,
                'text' => 'true',
                'mode' => $this->mode,
                'lang' => $language ?? $this->preferredLanguage,
            ], static fn ($value): bool => $value !== null && $value !== ''));

        if ($response->status() === 202) {
            return $this->pollTranscriptJob($response->json('jobId'), $language);
        }

        return $this->extractTranscriptFromResponse($response, $language);
    }

    private function pollTranscriptJob(mixed $jobId, ?string $language): TranscriptDocument
    {
        if (! is_string($jobId) || trim($jobId) === '') {
            throw new YoutubeTranscriptProviderException('Supadata transcript job response is missing a job ID.');
        }

        $http = Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->timeout($this->timeout)
            ->acceptJson()
            ->withHeaders([
                'x-api-key' => $this->apiKey,
            ]);

        for ($attempt = 0; $attempt < $this->maxPolls; $attempt++) {
            $response = $http->get("/transcript/{$jobId}");

            if ($response->failed() && $response->status() !== 200) {
                $this->throwForResponse($response);
            }

            $status = $response->json('status');

            if ($status === 'completed') {
                return $this->normalizeTranscriptContent($response->json('content'), $language);
            }

            if ($status === 'failed') {
                $message = $response->json('error.message')
                    ?? $response->json('message')
                    ?? 'Supadata transcript job failed.';

                throw new YoutubeTranscriptProviderException($message);
            }

            if ($attempt < $this->maxPolls - 1 && $this->pollIntervalMs > 0) {
                usleep($this->pollIntervalMs * 1000);
            }
        }

        throw new YoutubeTranscriptProviderException('Supadata transcript job did not complete within the polling limit.');
    }

    private function extractTranscriptFromResponse(\Illuminate\Http\Client\Response $response, ?string $language): TranscriptDocument
    {
        if ($response->status() === 206 || $response->failed()) {
            $this->throwForResponse($response);
        }

        return $this->normalizeTranscriptContent($response->json('content'), $language);
    }

    private function throwForResponse(\Illuminate\Http\Client\Response $response): never
    {
        $message = $response->json('message')
            ?? $response->json('details')
            ?? $response->reason()
            ?? 'Supadata transcript request failed.';

        if ($response->status() === 206) {
            throw new TranscriptUnavailableException($message, $response->status());
        }

        if ($response->status() === 400) {
            throw new InvalidYoutubeUrlException($message, $response->status());
        }

        throw new YoutubeTranscriptProviderException($message, $response->status());
    }

    private function normalizeTranscriptContent(mixed $content, ?string $language): TranscriptDocument
    {
        if (is_string($content)) {
            $text = trim($content);
            if ($text !== '') {
                return TranscriptDocument::fromPlainText($text, $language, 'youtube');
            }
        }

        if (is_array($content)) {
            $segments = [];
            foreach ($content as $index => $segment) {
                if (! is_array($segment)) {
                    continue;
                }

                $text = trim((string) ($segment['text'] ?? ''));
                if ($text === '') {
                    continue;
                }

                // Supadata's offset and duration are explicitly milliseconds.
                // Keep fallback fields separate: start/startTime are treated
                // as seconds because they come from non-Supadata adapters.
                $startMs = array_key_exists('offset', $segment)
                    ? $this->integerMilliseconds($segment['offset'])
                    : $this->secondsToMilliseconds($segment['start'] ?? $segment['startTime'] ?? 0);
                $durationMs = $this->integerMilliseconds($segment['duration'] ?? 0);
                $endMs = array_key_exists('end', $segment)
                    ? $this->integerMilliseconds($segment['end'])
                    : 0;
                if ($endMs <= $startMs) {
                    $endMs = $durationMs > 0 ? $startMs + $durationMs : $startMs;
                }

                $segments[] = new TranscriptSegmentData(
                    startMs: $startMs,
                    endMs: $endMs,
                    text: $text,
                    sourceKey: isset($segment['id']) ? (string) $segment['id'] : (string) $index,
                );
            }

            if ($segments !== []) {
                return new TranscriptDocument(
                    fullText: trim(implode(' ', array_map(static fn (TranscriptSegmentData $segment): string => $segment->text, $segments))),
                    segments: $segments,
                    language: $language,
                    source: 'youtube',
                );
            }
        }

        throw new YoutubeTranscriptProviderException('Supadata transcript response did not contain transcript text.');
    }

    private function integerMilliseconds(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) round((float) $value)) : 0;
    }

    private function secondsToMilliseconds(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) round((float) $value * 1000)) : 0;
    }

    private function assertValidYoutubeUrl(string $url): void
    {
        if ($this->extractVideoId($url) === null) {
            throw new InvalidYoutubeUrlException('Invalid YouTube URL: cannot extract video ID.');
        }
    }

    private function extractVideoId(string $url): ?string
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        $path = trim((string) ($parts['path'] ?? ''), '/');

        if ($host === 'youtu.be') {
            return $this->normalizeVideoId($path);
        }

        $isYoutubeHost = in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)
            || str_ends_with($host, '.youtube.com');
        $isYoutubeNoCookieHost = in_array($host, ['youtube-nocookie.com', 'www.youtube-nocookie.com'], true)
            || str_ends_with($host, '.youtube-nocookie.com');

        if (! $isYoutubeHost && ! $isYoutubeNoCookieHost) {
            return null;
        }

        if ($path === 'watch') {
            parse_str((string) ($parts['query'] ?? ''), $query);

            return $this->normalizeVideoId($query['v'] ?? null);
        }

        foreach (['embed/', 'shorts/', 'live/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $this->normalizeVideoId(substr($path, strlen($prefix)));
            }
        }

        return null;
    }

    private function normalizeVideoId(mixed $videoId): ?string
    {
        if (! is_string($videoId)) {
            return null;
        }

        $candidate = trim(strtok($videoId, '?&/'));

        return preg_match('/^[A-Za-z0-9_-]{11}$/', $candidate) === 1
            ? $candidate
            : null;
    }
}
