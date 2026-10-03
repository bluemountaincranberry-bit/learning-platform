<?php

namespace App\Modules\Content\Infrastructure\Pdf;

use App\Exceptions\PdfExtractionException;
use App\Modules\Content\Application\Contracts\PdfTextExtractorInterface;
use Symfony\Component\Process\Process;

/**
 * Reads a PDF's text layer with poppler's `pdftotext` (poppler-utils in the
 * app image). Replaced a hand-rolled parser that returned "Adobe UCS"
 * garbage for real lesson PDFs with CID fonts (VIK-42).
 *
 * `-layout` keeps a word list's columns side by side, so "word  translation"
 * stays on one line; whitespace is then compacted to keep the text short for
 * the lesson notes and the model. Scanned PDFs have no text layer — OCR is
 * VIK-18.
 */
class PopplerPdfTextExtractor implements PdfTextExtractorInterface
{
    public function extractFromPath(string $absolutePath): string
    {
        if (! is_file($absolutePath)) {
            throw new PdfExtractionException("PDF file not found: {$absolutePath}");
        }

        $process = new Process(['pdftotext', '-layout', '-enc', 'UTF-8', $absolutePath, '-']);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new PdfExtractionException('Could not read this PDF (the file may be damaged or password-protected).');
        }

        $text = $this->compact($process->getOutput());

        if ($text === '') {
            throw new PdfExtractionException('This PDF is a scan; photo/OCR support is coming (VIK-18).');
        }

        return $text;
    }

    private function compact(string $text): string
    {
        $lines = array_map(
            fn (string $line): string => preg_replace('/ {2,}/', '  ', trim($line)) ?? trim($line),
            // /u matters: without it \R matches the 0x85 byte inside Cyrillic "х".
            preg_split('/\R|\f/u', $text) ?: [],
        );

        return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)) ?? '');
    }
}
