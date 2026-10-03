<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

function makeContentWithTranslatedLexeme(): array
{
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'C', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready',
    ]);
    $content->lexemes()->create(['type' => 'word', 'text' => 'run', 'sort_order' => 1]);
    // ContentLexeme::booted() auto-syncs to a canonical lexeme and sets its
    // lexeme_id directly — no separate link row to create anymore.
    $lexeme = Lexeme::query()->where('normalized_lemma', 'run')->where('language', 'en')->first();
    $lexeme->translations()->create(['language' => 'ru', 'translation' => 'бежать', 'is_primary' => true]);
    $lexeme->translations()->create(['language' => 'es', 'translation' => 'correr', 'is_primary' => true]);

    return [$content, $lexeme];
}

test('lexemes endpoint returns the translation matching the requesting users language', function () {
    [$content] = makeContentWithTranslatedLexeme();
    $user = User::factory()->create(['translation_language' => 'es']);
    $user->assignRole('user');

    $response = $this->actingAs($user)->getJson("/api/content/{$content->id}/lexemes")->assertOk();

    $lexemes = collect($response->json('lexemes'));
    expect($lexemes->firstWhere('text', 'run')['translation'])->toBe('correr');
});

test('lexemes endpoint returns a different translation for a different users language', function () {
    [$content] = makeContentWithTranslatedLexeme();
    $user = User::factory()->create(['translation_language' => 'ru']);
    $user->assignRole('user');

    $response = $this->actingAs($user)->getJson("/api/content/{$content->id}/lexemes")->assertOk();

    $lexemes = collect($response->json('lexemes'));
    expect($lexemes->firstWhere('text', 'run')['translation'])->toBe('бежать');
});

test('lexemes endpoint returns null translation when the users language has no match', function () {
    [$content] = makeContentWithTranslatedLexeme();
    $user = User::factory()->create(['translation_language' => 'de']);
    $user->assignRole('user');

    $response = $this->actingAs($user)->getJson("/api/content/{$content->id}/lexemes")->assertOk();

    $lexemes = collect($response->json('lexemes'));
    expect($lexemes->firstWhere('text', 'run')['translation'])->toBeNull();
});

test('lexemes endpoint falls back to the global config default when the user has no translation_language set', function () {
    config(['ai.analysis.translation_language' => 'ru']);
    [$content] = makeContentWithTranslatedLexeme();
    $user = User::factory()->create(['translation_language' => null]);
    $user->assignRole('user');

    $response = $this->actingAs($user)->getJson("/api/content/{$content->id}/lexemes")->assertOk();

    $lexemes = collect($response->json('lexemes'));
    expect($lexemes->firstWhere('text', 'run')['translation'])->toBe('бежать');
});
