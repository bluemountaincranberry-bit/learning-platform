<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;

test('content has many lexemes', function () {
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'draft',
    ]);

    $content->lexemes()->create([
        'type' => ContentLexeme::TYPE_WORD,
        'text' => 'hello',
        'sort_order' => 1,
    ]);
    $content->lexemes()->create([
        'type' => ContentLexeme::TYPE_WORD,
        'text' => 'world',
        'sort_order' => 2,
    ]);

    $content->refresh();
    expect($content->lexemes)->toHaveCount(2);
    expect($content->lexemes->pluck('text')->toArray())->toBe(['hello', 'world']);
    expect($content->lexemes->first()->canonicalLexeme)->not->toBeNull();
});
