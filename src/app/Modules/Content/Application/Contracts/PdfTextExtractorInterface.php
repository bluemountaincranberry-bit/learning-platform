<?php

namespace App\Modules\Content\Application\Contracts;

interface PdfTextExtractorInterface
{
    /**
     * Extracts plain text from a PDF file on disk.
     *
     * @throws \App\Exceptions\PdfExtractionException
     */
    public function extractFromPath(string $absolutePath): string;
}
