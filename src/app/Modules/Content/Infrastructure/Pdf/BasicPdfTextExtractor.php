<?php

namespace App\Modules\Content\Infrastructure\Pdf;

use App\Exceptions\PdfExtractionException;
use App\Modules\Content\Application\Contracts\PdfTextExtractorInterface;

/**
 * Dependency-free PDF text-layer extraction: scans raw PDF bytes for content
 * streams (inflating FlateDecode ones), then pulls text out of Tj/TJ
 * text-showing operators.
 *
 * This exists because this codebase's sandbox has no route to GitHub (only
 * to packagist.org's metadata API), and nearly every PHP PDF library ships
 * its dist zip via GitHub — `composer require smalot/pdfparser` cannot
 * complete here. In a normal dev/CI environment with full internet access,
 * swap this for smalot/pdfparser (much better font/encoding handling) —
 * that's a single class behind PdfTextExtractorInterface, nothing else
 * changes. Known gaps versus a real parser: custom CID/Type0 font encodings
 * may produce garbled text, and scanned/image-only PDFs return no text at
 * all (there is no OCR here).
 */
class BasicPdfTextExtractor implements PdfTextExtractorInterface
{
    public function extractFromPath(string $absolutePath): string
    {
        if (! is_file($absolutePath)) {
            throw new PdfExtractionException("PDF file not found: {$absolutePath}");
        }

        $raw = file_get_contents($absolutePath);
        if ($raw === false || $raw === '') {
            throw new PdfExtractionException('Could not read PDF file.');
        }

        $text = trim($this->extractFromBytes($raw));

        if ($text === '') {
            throw new PdfExtractionException('PDF contains no extractable text (it may be a scanned image).');
        }

        return $text;
    }

    private function extractFromBytes(string $raw): string
    {
        $chunks = [];

        foreach ($this->streamBlocks($raw) as [$dict, $body]) {
            $content = str_contains($dict, '/FlateDecode') ? @gzuncompress($body) : $body;

            if ($content === false || $content === null) {
                continue;
            }

            $chunks[] = $this->extractShownText($content);
        }

        return implode("\n", array_filter($chunks, fn (string $chunk): bool => trim($chunk) !== ''));
    }

    /**
     * @return array<int, array{0: string, 1: string}> pairs of [stream dictionary, raw stream body]
     */
    private function streamBlocks(string $raw): array
    {
        $blocks = [];

        if (preg_match_all('/(<<[^>]*>>)\s*stream\r?\n(.*?)\r?\n?endstream/s', $raw, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        foreach ($matches as $match) {
            $blocks[] = [$match[1], $match[2]];
        }

        return $blocks;
    }

    private function extractShownText(string $content): string
    {
        $out = [];

        // (escaped literal string) Tj  |  [(str) num (str) ...] TJ
        if (preg_match_all('/\((?:\\\\.|[^()\\\\])*\)|\[(?:[^\[\]]|\[[^\[\]]*\])*\]\s*TJ/s', $content, $matches)) {
            foreach ($matches[0] as $chunk) {
                if ($chunk[0] === '[') {
                    if (preg_match_all('/\((?:\\\\.|[^()\\\\])*\)/s', $chunk, $inner)) {
                        foreach ($inner[0] as $literal) {
                            $out[] = $this->decodeLiteral($literal);
                        }
                    }
                } else {
                    $out[] = $this->decodeLiteral($chunk);
                }
            }
        }

        return implode(' ', array_filter($out, fn (string $s): bool => $s !== ''));
    }

    private function decodeLiteral(string $literal): string
    {
        $inner = substr($literal, 1, -1);

        $decoded = preg_replace_callback('/\\\\([nrtbf()\\\\]|[0-7]{1,3})/', function (array $m): string {
            return match ($m[1]) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\x0C",
                '(' => '(',
                ')' => ')',
                '\\' => '\\',
                default => chr((int) octdec($m[1])),
            };
        }, $inner);

        return $decoded ?? $inner;
    }
}
