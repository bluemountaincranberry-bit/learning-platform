<?php

use App\Modules\Content\Application\ContentTokenizer;

test('tokenizer normalizes text and deduplicates', function () {
    $tokenizer = new ContentTokenizer();

    $text = "Hello, HELLO! It's test-case. hello? it's TEST-case.";
    $tokens = $tokenizer->tokenize($text);

    expect($tokens)->toBe([
        'hello',
        "it's",
        'test',
        'case',
    ]);
});

test('tokenizer obeys separators and max tokens limit', function () {
    $tokenizer = new ContentTokenizer();

    $parts = array_map(fn ($i) => "word{$i}", range(0, ContentTokenizer::MAX_TOKENS + 10));
    $source = implode(' ', $parts);
    $tokens = $tokenizer->tokenize($source);

    expect($tokens)->toHaveCount(ContentTokenizer::MAX_TOKENS);
});
