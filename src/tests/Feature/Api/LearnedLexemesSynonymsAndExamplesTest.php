<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\User\Models\User;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

test('learned lexemes endpoint returns associations of all types', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'C', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready',
    ]);
    $contentLexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'run', 'sort_order' => 1]);
    $canonical = Lexeme::query()->where('normalized_lemma', 'run')->first();
    $sprint = Lexeme::query()->create(['slug' => 'sprint', 'language' => 'en', 'lemma' => 'sprint', 'normalized_lemma' => 'sprint']);
    $canonical->associations()->create(['related_lexeme_id' => $sprint->id, 'type' => 'synonym', 'sort_order' => 1]);
    UserLexemeProgress::query()->create(['user_id' => $user->id, 'content_lexeme_id' => $contentLexeme->id, 'learned_at' => now()]);

    $response = $this->actingAs($user)->getJson('/api/me/learned-lexemes')->assertOk();

    expect($response->json('data.0.associations'))->toBe([
        ['lemma' => 'sprint', 'type' => 'synonym'],
    ]);
});

test('learned lexemes endpoint returns multiple examples ordered content-scoped first', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'C', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready',
    ]);
    $contentLexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'run', 'sort_order' => 1]);
    $canonical = Lexeme::query()->where('normalized_lemma', 'run')->first();
    $canonical->examples()->create(['content_id' => null, 'language' => 'en', 'example' => 'global', 'sort_order' => 1]);
    $canonical->examples()->create(['content_id' => $content->id, 'language' => 'en', 'example' => 'scoped', 'sort_order' => 9]);
    UserLexemeProgress::query()->create(['user_id' => $user->id, 'content_lexeme_id' => $contentLexeme->id, 'learned_at' => now()]);

    $response = $this->actingAs($user)->getJson('/api/me/learned-lexemes')->assertOk();

    expect(array_column($response->json('data.0.examples'), 'example'))->toBe(['scoped', 'global']);
});

test('learned lexemes endpoint returns empty arrays when there are no examples or associations', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'C', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready',
    ]);
    $contentLexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'run', 'sort_order' => 1]);
    UserLexemeProgress::query()->create(['user_id' => $user->id, 'content_lexeme_id' => $contentLexeme->id, 'learned_at' => now()]);

    $response = $this->actingAs($user)->getJson('/api/me/learned-lexemes')->assertOk();

    expect($response->json('data.0.examples'))->toBe([]);
    expect($response->json('data.0.associations'))->toBe([]);
});
