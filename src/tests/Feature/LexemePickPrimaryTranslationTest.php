<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\Lexeme;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeLexemeForTranslationPicking(): Lexeme
{
    return Lexeme::query()->create([
        'slug' => 'en-run', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run', 'status' => Lexeme::STATUS_PUBLISHED,
    ]);
}

test('pickPrimaryTranslation prefers the content-scoped primary over the global one', function () {
    $lexeme = makeLexemeForTranslationPicking();
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'C', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready',
    ]);
    $lexeme->translations()->create(['content_id' => null, 'language' => 'ru', 'translation' => 'бежать (общий)', 'is_primary' => true]);
    $lexeme->translations()->create(['content_id' => $content->id, 'language' => 'ru', 'translation' => 'бежать (из видео)', 'is_primary' => true]);

    $picked = Lexeme::pickPrimaryTranslation($lexeme->translations, $content->id, 'ru');

    expect($picked->translation)->toBe('бежать (из видео)');
});

test('pickPrimaryTranslation falls back to the global primary when no content-scoped match exists', function () {
    $lexeme = makeLexemeForTranslationPicking();
    $lexeme->translations()->create(['content_id' => null, 'language' => 'ru', 'translation' => 'бежать (общий)', 'is_primary' => true]);

    $picked = Lexeme::pickPrimaryTranslation($lexeme->translations, 5, 'ru');

    expect($picked->translation)->toBe('бежать (общий)');
});

test('pickPrimaryTranslation returns null when no translation exists in the requested language, even if one exists in another', function () {
    $lexeme = makeLexemeForTranslationPicking();
    $lexeme->translations()->create(['content_id' => null, 'language' => 'ru', 'translation' => 'бежать', 'is_primary' => true]);

    $picked = Lexeme::pickPrimaryTranslation($lexeme->translations, null, 'es');

    expect($picked)->toBeNull();
});

test('pickPrimaryTranslation returns null with no translations at all', function () {
    $lexeme = makeLexemeForTranslationPicking();

    expect(Lexeme::pickPrimaryTranslation($lexeme->translations, null, 'ru'))->toBeNull();
});
