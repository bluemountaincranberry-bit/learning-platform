<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\User\Models\User;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

test('get learned lexemes requires auth', function () {
    $this->getJson('/api/me/learned-lexemes')
        ->assertUnauthorized();
});

test('user sees only own learned lexemes', function () {
    $userA = User::factory()->create();
    $userA->assignRole('user');
    $userB = User::factory()->create();
    $userB->assignRole('user');

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'C',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $lexeme1 = $content->lexemes()->create(['type' => 'word', 'text' => 'hello', 'sort_order' => 1]);
    $lexeme2 = $content->lexemes()->create(['type' => 'word', 'text' => 'world', 'sort_order' => 2]);

    UserLexemeProgress::query()->create([
        'user_id' => $userA->id,
        'content_lexeme_id' => $lexeme1->id,
        'learned_at' => now(),
    ]);
    UserLexemeProgress::query()->create([
        'user_id' => $userB->id,
        'content_lexeme_id' => $lexeme2->id,
        'learned_at' => now(),
    ]);

    $response = $this->actingAs($userA)->getJson('/api/me/learned-lexemes')->assertOk();

    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['lexeme'])->toBe('hello');
    expect($data[0]['contexts'])->toHaveCount(1);
    expect($data[0]['contexts'][0]['content_id'])->toBe($content->id);
    expect($data[0]['contexts'][0]['content_title'])->toBe('C');
    expect($data[0]['contexts'][0]['language'])->toBe('en');
});

test('learned lexemes filters by language', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $contentEn = Content::query()->create([
        'type' => 'youtube',
        'title' => 'EN',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $contentEs = Content::query()->create([
        'type' => 'youtube',
        'title' => 'ES',
        'language' => 'es',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $lexEn = $contentEn->lexemes()->create(['type' => 'word', 'text' => 'hello', 'sort_order' => 1]);
    $lexEs = $contentEs->lexemes()->create(['type' => 'word', 'text' => 'hola', 'sort_order' => 1]);

    UserLexemeProgress::query()->create([
        'user_id' => $user->id,
        'content_lexeme_id' => $lexEn->id,
        'learned_at' => now(),
    ]);
    UserLexemeProgress::query()->create([
        'user_id' => $user->id,
        'content_lexeme_id' => $lexEs->id,
        'learned_at' => now(),
    ]);

    $response = $this->actingAs($user)->getJson('/api/me/learned-lexemes?language=en')->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['lexeme'])->toBe('hello');

    $response2 = $this->actingAs($user)->getJson('/api/me/learned-lexemes?language=es')->assertOk();
    expect($response2->json('data'))->toHaveCount(1);
    expect($response2->json('data.0.lexeme'))->toBe('hola');
});

test('learned lexemes filters by content_id', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $content1 = Content::query()->create([
        'type' => 'youtube',
        'title' => 'First',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $content2 = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Second',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $lex1 = $content1->lexemes()->create(['type' => 'word', 'text' => 'one', 'sort_order' => 1]);
    $lex2 = $content2->lexemes()->create(['type' => 'word', 'text' => 'two', 'sort_order' => 1]);

    UserLexemeProgress::query()->create([
        'user_id' => $user->id,
        'content_lexeme_id' => $lex1->id,
        'learned_at' => now(),
    ]);
    UserLexemeProgress::query()->create([
        'user_id' => $user->id,
        'content_lexeme_id' => $lex2->id,
        'learned_at' => now(),
    ]);

    $response = $this->actingAs($user)->getJson('/api/me/learned-lexemes?content_id='.$content1->id)->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['contexts'])->toHaveCount(1);
    expect($data[0]['contexts'][0]['content_id'])->toBe($content1->id);
    expect($data[0]['contexts'][0]['content_title'])->toBe('First');
});

test('learned lexemes returns all content contexts for a shared lexeme', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $content1 = Content::query()->create([
        'type' => 'youtube',
        'title' => 'First',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $content2 = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Second',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $lexeme1 = $content1->lexemes()->create(['type' => 'word', 'text' => 'shared', 'sort_order' => 1]);
    $content2->lexemes()->create(['type' => 'word', 'text' => 'shared', 'sort_order' => 1]);

    UserLexemeProgress::query()->create([
        'user_id' => $user->id,
        'content_lexeme_id' => $lexeme1->id,
        'learned_at' => now(),
    ]);

    $response = $this->actingAs($user)->getJson('/api/me/learned-lexemes')->assertOk();
    $data = $response->json('data');

    expect($data)->toHaveCount(1);
    expect($data[0]['contexts'])->toHaveCount(2);
    expect(collect($data[0]['contexts'])->pluck('content_title')->all())->toContain('First', 'Second');
});

test('learned lexemes returns meta for pagination', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'C',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $lexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'hello', 'sort_order' => 1]);
    UserLexemeProgress::query()->create([
        'user_id' => $user->id,
        'content_lexeme_id' => $lexeme->id,
        'learned_at' => now(),
    ]);

    $response = $this->actingAs($user)->getJson('/api/me/learned-lexemes?per_page=5')->assertOk();

    expect($response->json('meta'))->toHaveKeys(['current_page', 'per_page', 'total']);
    expect($response->json('meta.per_page'))->toBe(5);
    expect($response->json('meta.total'))->toBe(1);
});

test('learned lexemes returns the translation matching the requesting users language', function () {
    $user = User::factory()->create(['translation_language' => 'es']);
    $user->assignRole('user');
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'C', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready',
    ]);
    $lexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'run', 'sort_order' => 1]);
    $canonical = \App\Modules\Content\Domain\Models\Lexeme::query()->where('normalized_lemma', 'run')->first();
    $canonical->translations()->create(['language' => 'ru', 'translation' => 'бежать', 'is_primary' => true]);
    $canonical->translations()->create(['language' => 'es', 'translation' => 'correr', 'is_primary' => true]);
    UserLexemeProgress::query()->create(['user_id' => $user->id, 'content_lexeme_id' => $lexeme->id, 'learned_at' => now()]);

    $response = $this->actingAs($user)->getJson('/api/me/learned-lexemes')->assertOk();

    expect($response->json('data.0.translation'))->toBe('correr');
});

test('learned lexemes returns null translation when the users language has no match', function () {
    $user = User::factory()->create(['translation_language' => 'de']);
    $user->assignRole('user');
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'C', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready',
    ]);
    $lexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'run', 'sort_order' => 1]);
    $canonical = \App\Modules\Content\Domain\Models\Lexeme::query()->where('normalized_lemma', 'run')->first();
    $canonical->translations()->create(['language' => 'ru', 'translation' => 'бежать', 'is_primary' => true]);
    UserLexemeProgress::query()->create(['user_id' => $user->id, 'content_lexeme_id' => $lexeme->id, 'learned_at' => now()]);

    $response = $this->actingAs($user)->getJson('/api/me/learned-lexemes')->assertOk();

    expect($response->json('data.0.translation'))->toBeNull();
});
