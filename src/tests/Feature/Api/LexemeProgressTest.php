<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\LexemeExample;
use App\Modules\Content\Domain\Models\LexemeTranslation;
use App\Modules\User\Models\User;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

test('mark learned requires auth', function () {
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'C',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $lexeme = $content->lexemes()->create([
        'type' => 'word',
        'text' => 'hello',
        'sort_order' => 1,
    ]);

    $this->postJson("/api/content/lexemes/{$lexeme->id}/mark-learned")
        ->assertUnauthorized();
});

test('authenticated user can mark lexeme learned', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'C',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $lexeme = $content->lexemes()->create([
        'type' => 'word',
        'text' => 'hello',
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->postJson("/api/content/lexemes/{$lexeme->id}/mark-learned")
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect(UserLexemeProgress::query()->where('user_id', $user->id)->where('content_lexeme_id', $lexeme->id)->exists())->toBeTrue();
});

test('mark learned is idempotent', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'C',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $lexeme = $content->lexemes()->create([
        'type' => 'word',
        'text' => 'hello',
        'sort_order' => 1,
    ]);

    $this->actingAs($user)->postJson("/api/content/lexemes/{$lexeme->id}/mark-learned")->assertOk();
    $this->actingAs($user)->postJson("/api/content/lexemes/{$lexeme->id}/mark-learned")->assertOk();

    expect(UserLexemeProgress::query()->where('user_id', $user->id)->where('content_lexeme_id', $lexeme->id)->count())->toBe(1);
});

test('get content lexemes requires auth and returns learned flags', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'C',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $lexeme1 = $content->lexemes()->create(['type' => 'word', 'text' => 'hello', 'sort_order' => 1]);
    $content->lexemes()->create(['type' => 'word', 'text' => 'world', 'sort_order' => 2]);

    UserLexemeProgress::query()->create([
        'user_id' => $user->id,
        'content_lexeme_id' => $lexeme1->id,
        'learned_at' => now(),
    ]);

    $this->actingAs($user)
        ->getJson("/api/content/{$content->id}/lexemes")
        ->assertOk()
        ->assertJsonPath('lexemes.0.text', 'hello')
        ->assertJsonPath('lexemes.0.learned', true)
        ->assertJsonPath('lexemes.1.text', 'world')
        ->assertJsonPath('lexemes.1.learned', false);
});

test('get content lexemes returns translation, preferring content-scoped over global example', function () {
    $user = User::factory()->create(['translation_language' => 'en']);
    $user->assignRole('user');

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'C',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $otherContent = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Other',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);

    $helloLexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'hello', 'sort_order' => 1]);
    $worldLexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'world', 'sort_order' => 2]);

    $helloCanonical = $helloLexeme->canonicalLexeme;

    // Global primary example — should be overridden by the content-scoped one below.
    LexemeExample::query()->create([
        'lexeme_id' => $helloCanonical->id,
        'content_id' => null,
        'language' => 'en',
        'example' => 'Global example for hello.',
        'is_primary' => true,
        'sort_order' => 1,
    ]);

    // Content-scoped primary for this content — must win.
    LexemeExample::query()->create([
        'lexeme_id' => $helloCanonical->id,
        'content_id' => $content->id,
        'language' => 'en',
        'example' => 'Hello, how are you?',
        'is_primary' => true,
        'sort_order' => 2,
    ]);

    // Primary example scoped to a *different* content — must not leak in.
    LexemeExample::query()->create([
        'lexeme_id' => $helloCanonical->id,
        'content_id' => $otherContent->id,
        'language' => 'en',
        'example' => 'Wrong content example.',
        'is_primary' => true,
        'sort_order' => 3,
    ]);

    // Word gloss — separate from the example sentence's own translation.
    // Global primary — should be overridden by the content-scoped one below.
    LexemeTranslation::query()->create([
        'lexeme_id' => $helloCanonical->id,
        'content_id' => null,
        'language' => 'en',
        'translation' => 'global translation',
        'is_primary' => true,
        'sort_order' => 1,
    ]);
    // Content-scoped primary for this content — must win.
    LexemeTranslation::query()->create([
        'lexeme_id' => $helloCanonical->id,
        'content_id' => $content->id,
        'language' => 'en',
        'translation' => 'content-scoped translation',
        'is_primary' => true,
        'sort_order' => 2,
    ]);
    // Primary translation scoped to a *different* content — must not leak in.
    LexemeTranslation::query()->create([
        'lexeme_id' => $helloCanonical->id,
        'content_id' => $otherContent->id,
        'language' => 'en',
        'translation' => 'wrong content translation',
        'is_primary' => true,
        'sort_order' => 3,
    ]);

    // 'world' has no examples/translations at all — must degrade to null, not error.
    expect($worldLexeme->canonicalLexeme->examples)->toHaveCount(0);

    $response = $this->actingAs($user)
        ->getJson("/api/content/{$content->id}/lexemes")
        ->assertOk()
        ->assertJsonPath('lexemes.0.text', 'hello')
        ->assertJsonPath('lexemes.0.translation', 'content-scoped translation')
        ->assertJsonPath('lexemes.0.example', 'Hello, how are you?')
        ->assertJsonPath('lexemes.1.text', 'world')
        ->assertJsonPath('lexemes.1.translation', null)
        ->assertJsonPath('lexemes.1.example', null);

    $response->assertOk();
});

test('get content lexemes falls back to global primary example when no content-scoped one exists', function () {
    $user = User::factory()->create(['translation_language' => 'en']);
    $user->assignRole('user');

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'C',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);

    $lexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'hello', 'sort_order' => 1]);
    $canonical = $lexeme->canonicalLexeme;

    LexemeExample::query()->create([
        'lexeme_id' => $canonical->id,
        'content_id' => null,
        'language' => 'en',
        'example' => 'Global example for hello.',
        'is_primary' => true,
        'sort_order' => 1,
    ]);
    LexemeTranslation::query()->create([
        'lexeme_id' => $canonical->id,
        'content_id' => null,
        'language' => 'en',
        'translation' => 'global translation',
        'is_primary' => true,
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->getJson("/api/content/{$content->id}/lexemes")
        ->assertOk()
        ->assertJsonPath('lexemes.0.translation', 'global translation')
        ->assertJsonPath('lexemes.0.example', 'Global example for hello.');
});

test('lexemes endpoint returns 404 for non-ready content', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Draft',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'draft',
    ]);

    $this->actingAs($user)->getJson("/api/content/{$content->id}/lexemes")->assertNotFound();
});
