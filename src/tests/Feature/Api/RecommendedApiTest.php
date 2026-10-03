<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('recommended contents requires auth', function () {
    $response = $this->getJson('/api/ai/recommended/contents');

    $response->assertStatus(401);
});

test('recommended lexemes requires auth', function () {
    $response = $this->getJson('/api/ai/recommended/lexemes');

    $response->assertStatus(401);
});

test('recommended contents returns 200 and list shape', function () {
    $user = User::factory()->create(['ui_language' => 'en']);
    Content::query()->create([
        'type' => 'song',
        'title' => 'Test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ])->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'hello', 'sort_order' => 1]);

    $response = $this->actingAs($user)->getJson('/api/ai/recommended/contents?limit=5');

    $response->assertStatus(200)
        ->assertJsonStructure(['data' => [['id', 'title']]]);
});

test('recommended contents returns empty list when no recommendations', function () {
    $user = User::factory()->create(['ui_language' => 'en']);

    $response = $this->actingAs($user)->getJson('/api/ai/recommended/contents');

    $response->assertStatus(200)->assertJsonPath('data', []);
});

test('recommended lexemes returns 200 and list shape', function () {
    $user = User::factory()->create(['ui_language' => 'en']);
    $content = Content::query()->create([
        'type' => 'song',
        'title' => 'Test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'word', 'sort_order' => 1]);

    $response = $this->actingAs($user)->getJson('/api/ai/recommended/lexemes?limit=10');

    $response->assertStatus(200)
        ->assertJsonStructure(['data' => [['id', 'text', 'content_id']]])
        ->assertJsonPath('data.0.content_id', $content->id);
});

test('recommended lexemes returns empty list when no recommendations', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/ai/recommended/lexemes');

    $response->assertStatus(200)->assertJsonPath('data', []);
});

test('integration: recommended API respects user progress and not-learned rule', function () {
    $user = User::factory()->create(['ui_language' => 'en']);
    $contentFull = Content::query()->create([
        'type' => 'song',
        'title' => 'Fully learned',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $lexFull1 = $contentFull->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'a', 'sort_order' => 1]);
    $lexFull2 = $contentFull->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'b', 'sort_order' => 2]);
    UserLexemeProgress::query()->create(['user_id' => $user->id, 'content_lexeme_id' => $lexFull1->id, 'learned_at' => now()]);
    UserLexemeProgress::query()->create(['user_id' => $user->id, 'content_lexeme_id' => $lexFull2->id, 'learned_at' => now()]);

    $contentPartial = Content::query()->create([
        'type' => 'song',
        'title' => 'Has unlearned',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $lexPartial1 = $contentPartial->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'x', 'sort_order' => 1]);
    $contentPartial->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'y', 'sort_order' => 2]);
    UserLexemeProgress::query()->create(['user_id' => $user->id, 'content_lexeme_id' => $lexPartial1->id, 'learned_at' => now()]);

    $contentsRes = $this->actingAs($user)->getJson('/api/ai/recommended/contents');
    $contentsRes->assertStatus(200);
    $contentIds = collect($contentsRes->json('data'))->pluck('id')->all();
    expect($contentIds)->not->toContain($contentFull->id)
        ->and($contentIds)->toContain($contentPartial->id);

    $lexemesRes = $this->actingAs($user)->getJson('/api/ai/recommended/lexemes');
    $lexemesRes->assertStatus(200);
    $lexemeIds = collect($lexemesRes->json('data'))->pluck('id')->all();
    expect($lexemeIds)->not->toContain($lexPartial1->id);
});
