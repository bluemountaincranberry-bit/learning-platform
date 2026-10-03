<?php

use App\Exceptions\PdfExtractionException;
use App\Modules\Content\Infrastructure\Pdf\BasicPdfTextExtractor;

function writeMinimalPdf(string $streamBody, bool $compress = false): string
{
    $path = tempnam(sys_get_temp_dir(), 'pdftest').'.pdf';
    $body = $compress ? gzcompress($streamBody) : $streamBody;
    $filter = $compress ? ' /Filter /FlateDecode' : '';

    $pdf = "%PDF-1.4\n"
        ."1 0 obj\n<< /Length ".strlen($body).$filter." >>\nstream\n".$body."\nendstream\nendobj\n"
        ."%%EOF";

    file_put_contents($path, $pdf);

    return $path;
}

test('extracts plain text from an uncompressed content stream', function () {
    $path = writeMinimalPdf('BT /F1 12 Tf 72 700 Td (Hello World) Tj ET');

    $text = (new BasicPdfTextExtractor)->extractFromPath($path);

    expect($text)->toBe('Hello World');

    unlink($path);
});

test('extracts text from a FlateDecode-compressed content stream', function () {
    $path = writeMinimalPdf('BT /F1 12 Tf 72 700 Td (gate) Tj 0 -20 Td (boarding pass) Tj ET', compress: true);

    $text = (new BasicPdfTextExtractor)->extractFromPath($path);

    expect($text)->toBe('gate boarding pass');

    unlink($path);
});

test('handles TJ arrays and escaped characters', function () {
    $path = writeMinimalPdf('BT [(He said \\(hi\\)) -250 (to me)] TJ ET');

    $text = (new BasicPdfTextExtractor)->extractFromPath($path);

    expect($text)->toBe('He said (hi) to me');

    unlink($path);
});

test('throws when the PDF has no extractable text', function () {
    $path = writeMinimalPdf('');

    (new BasicPdfTextExtractor)->extractFromPath($path);

    unlink($path);
})->throws(PdfExtractionException::class, 'no extractable text');

test('throws when the file does not exist', function () {
    (new BasicPdfTextExtractor)->extractFromPath('/tmp/does-not-exist-'.uniqid().'.pdf');
})->throws(PdfExtractionException::class, 'not found');
