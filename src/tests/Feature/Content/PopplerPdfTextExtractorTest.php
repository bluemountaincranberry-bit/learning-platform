<?php

use App\Exceptions\PdfExtractionException;
use App\Modules\Content\Application\Contracts\PdfTextExtractorInterface;
use App\Modules\Content\Infrastructure\Pdf\PopplerPdfTextExtractor;

function blankPagePdf(): string
{
    $path = tempnam(sys_get_temp_dir(), 'pdftest').'.pdf';

    file_put_contents($path, "%PDF-1.4\n"
        ."1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        ."2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        ."3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>\nendobj\n"
        ."trailer\n<< /Root 1 0 R >>\n%%EOF");

    return $path;
}

test('the container binds the poppler extractor', function () {
    expect(app(PdfTextExtractorInterface::class))->toBeInstanceOf(PopplerPdfTextExtractor::class);
});

test('extracts a real lesson word list with its translations', function () {
    $text = (new PopplerPdfTextExtractor)->extractFromPath(base_path('tests/Fixtures/pdf/wordlist-unit-1d.pdf'));

    expect($text)
        ->toContain('English in Action')
        ->toContain('to encourage smn to do smth')
        ->toContain('to discourage smn from doing smth')
        ->toContain('побуждать кого-либо делать что-либо')
        ->not->toContain('Adobe UCS');

    // A word and its translation stay on one line (layout mode), so the
    // lesson notes read as pairs rather than two separate columns.
    expect($text)->toMatch('/to encourage smn to do smth\s+побуждать/u');
});

test('a PDF without a text layer is reported as a scan', function () {
    $path = blankPagePdf();

    try {
        (new PopplerPdfTextExtractor)->extractFromPath($path);
    } finally {
        unlink($path);
    }
})->throws(PdfExtractionException::class, 'This PDF is a scan');

test('a file that is not a PDF gives a clear error', function () {
    $path = tempnam(sys_get_temp_dir(), 'pdftest').'.pdf';
    file_put_contents($path, 'not a pdf');

    try {
        (new PopplerPdfTextExtractor)->extractFromPath($path);
    } finally {
        unlink($path);
    }
})->throws(PdfExtractionException::class, 'Could not read this PDF');

test('throws when the file does not exist', function () {
    (new PopplerPdfTextExtractor)->extractFromPath('/tmp/does-not-exist-'.uniqid().'.pdf');
})->throws(PdfExtractionException::class, 'not found');
