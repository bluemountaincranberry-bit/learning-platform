<?php

use App\Modules\Ai\Application\TextChunker;

test('short text is a single chunk and empty text has none', function () {
    $chunker = new TextChunker;

    expect($chunker->chunk('hello world', 100))->toBe(['hello world'])
        ->and($chunker->chunk("  \n ", 100))->toBe([]);
});

test('long text is split on whole words and loses no word', function () {
    $words = array_map(fn ($i) => "word{$i}", range(1, 500));
    $text = implode(' ', $words);

    $chunks = (new TextChunker)->chunk($text, 200);

    expect(count($chunks))->toBeGreaterThan(1);
    foreach ($chunks as $chunk) {
        expect(mb_strlen($chunk))->toBeLessThanOrEqual(200);
    }
    expect(preg_split('/\s+/', implode(' ', $chunks)))->toBe($words);
});

test('prefers a line break when it leaves a reasonably full chunk', function () {
    $text = str_repeat('a', 150)."\n".str_repeat('b ', 100);

    $chunks = (new TextChunker)->chunk($text, 200);

    expect($chunks[0])->toBe(str_repeat('a', 150));
});
