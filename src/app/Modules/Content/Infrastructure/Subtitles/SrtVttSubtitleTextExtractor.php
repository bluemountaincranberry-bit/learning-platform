<?php

namespace App\Modules\Content\Infrastructure\Subtitles;

use App\Exceptions\SubtitleExtractionException;
use App\Modules\Content\Application\Contracts\SubtitleTextExtractorInterface;
use App\Modules\Content\Application\Transcript\TranscriptDocument;
use App\Modules\Content\Application\Transcript\TranscriptSegmentData;

/**
 * Parses .srt/.vtt dialogue into a plain-text compatibility field and timed
 * transcript segments for the learning workspace.
 */
class SrtVttSubtitleTextExtractor implements SubtitleTextExtractorInterface
{
    private const TIMESTAMP_PATTERN = '/^\d{2}:\d{2}:\d{2}[.,]\d{3}\s*-->\s*\d{2}:\d{2}:\d{2}[.,]\d{3}/';

    public function extractFromPath(string $absolutePath): string
    {
        return $this->extractSegmentsFromPath($absolutePath)->fullText;
    }

    public function extractSegmentsFromPath(string $absolutePath): TranscriptDocument
    {
        if (! is_file($absolutePath)) {
            throw new SubtitleExtractionException("Subtitle file not found: {$absolutePath}");
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if (! in_array($extension, ['srt', 'vtt'], true)) {
            throw new SubtitleExtractionException("Unsupported subtitle format: .{$extension} (only .srt and .vtt are supported)");
        }

        $raw = file_get_contents($absolutePath);
        if ($raw === false || trim($raw) === '') {
            throw new SubtitleExtractionException('Could not read subtitle file.');
        }

        $segments = $this->extractCues($raw);
        $text = trim(implode("\n", array_map(static fn (array $cue): string => $cue['text'], $segments)));

        if ($text === '') {
            throw new SubtitleExtractionException('Subtitle file contains no readable dialogue.');
        }

        return new TranscriptDocument(
            fullText: $text,
            segments: array_map(static fn (array $cue): TranscriptSegmentData => new TranscriptSegmentData(
                startMs: $cue['start_ms'],
                endMs: $cue['end_ms'],
                text: $cue['text'],
                sourceKey: $cue['source_key'],
            ), $segments),
            language: null,
            source: $extension,
        );
    }

    /** @return array<int, array{start_ms: int, end_ms: int, text: string, source_key: string|null}> */
    private function extractCues(string $raw): array
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $raw));
        $lineCount = count($lines);

        $cues = [];
        $currentCueLines = [];
        $currentStartMs = null;
        $currentEndMs = null;
        $currentSourceKey = null;

        for ($i = 0; $i < $lineCount; $i++) {
            $line = trim($lines[$i]);

            if ($line === '') {
                if ($currentCueLines !== []) {
                    $cues[] = [
                        'start_ms' => $currentStartMs ?? 0,
                        'end_ms' => $currentEndMs ?? 0,
                        'text' => implode(' ', $currentCueLines),
                        'source_key' => $currentSourceKey,
                    ];
                    $currentCueLines = [];
                    $currentStartMs = null;
                    $currentEndMs = null;
                    $currentSourceKey = null;
                }

                continue;
            }

            if (preg_match(self::TIMESTAMP_PATTERN, $line) === 1) {
                [$start, $end] = preg_split('/\s+-->\s+/', trim($line), 2);
                $currentStartMs = $this->parseTimestamp($start);
                $currentEndMs = $this->parseTimestamp(preg_replace('/\s+.*$/', '', $end));
                continue;
            }

            if (str_starts_with($line, 'WEBVTT') || str_starts_with($line, 'NOTE') || str_starts_with($line, 'STYLE')) {
                continue;
            }

            $nextLine = isset($lines[$i + 1]) ? trim($lines[$i + 1]) : '';
            $nextLineIsTimestamp = preg_match(self::TIMESTAMP_PATTERN, $nextLine) === 1;

            if (ctype_digit($line) || $nextLineIsTimestamp) {
                // SRT sequence number, or a VTT cue identifier immediately before a timing line.
                if ($nextLineIsTimestamp) {
                    $currentSourceKey = $line;
                }
                continue;
            }

            $text = trim((string) preg_replace('/<[^>]+>/', '', $line));
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);

            if ($text !== '') {
                $currentCueLines[] = $text;
            }
        }

        if ($currentCueLines !== []) {
            $cues[] = implode(' ', $currentCueLines);
        }

        // Overlapping cues in exported .srt files often repeat the previous
        // line verbatim while a new one fades in — collapse consecutive
        // duplicates so the transcript reads as continuous dialogue.
        $deduped = [];
        foreach ($cues as $cue) {
            $last = $deduped[count($deduped) - 1]['text'] ?? null;
            if ($last !== $cue['text']) {
                $deduped[] = $cue;
            }
        }

        return $deduped;
    }

    private function parseTimestamp(string $timestamp): int
    {
        [$hours, $minutes, $seconds] = explode(':', str_replace(',', '.', trim($timestamp)));
        [$wholeSeconds, $milliseconds] = array_pad(explode('.', $seconds, 2), 2, '0');

        return ((int) $hours * 3600 + (int) $minutes * 60 + (int) $wholeSeconds) * 1000 + (int) str_pad(substr($milliseconds, 0, 3), 3, '0');
    }
}
