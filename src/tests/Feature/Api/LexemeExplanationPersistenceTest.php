<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Content\Domain\Models\LexemeExplanation;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

function explainAiFake(): void
{
    Config::set('ai.enabled', true);
    Config::set('ai.provider', 'openai');
    Config::set('ai.openai.api_key', 'test-key');
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'A greeting.']],
            ],
        ], 200),
    ]);
}

function explainUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('user');

    return $user;
}

test('explain persists the explanation for the canonical lexeme', function () {
    explainAiFake();
    Queue::fake();
    $user = explainUser();
    $content = Content::factory()->create(['status' => 'ready', 'language' => 'en']);
    $lexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'hello', 'sort_order' => 1]);

    $this->actingAs($user)
        ->postJson("/api/lexemes/{$lexeme->id}/explain")
        ->assertOk()
        ->assertJsonPath('explanation', 'A greeting.');

    expect(LexemeExplanation::query()->where('lexeme_id', $lexeme->fresh()->lexeme_id)->where('language', 'en')->value('explanation'))
        ->toBe('A greeting.');
});

test('explain reuses the stored explanation without calling AI', function () {
    explainAiFake();
    Queue::fake();
    Config::set('ai.explain_cache_enabled', false);
    $user = explainUser();
    $content = Content::factory()->create(['status' => 'ready', 'language' => 'en']);
    $lexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'hello', 'sort_order' => 1]);

    $this->actingAs($user)->postJson("/api/lexemes/{$lexeme->id}/explain")->assertOk();
    $this->actingAs($user)->postJson("/api/lexemes/{$lexeme->id}/explain")->assertOk()->assertJsonPath('explanation', 'A greeting.');

    Http::assertSentCount(1);
});

test('dictionary show includes the stored explanation', function () {
    explainAiFake();
    Queue::fake();
    $user = explainUser();
    $content = Content::factory()->create(['status' => 'ready', 'language' => 'en', 'title' => 'Test video']);
    $lexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'hello', 'sort_order' => 1]);

    $this->actingAs($user)->postJson("/api/lexemes/{$lexeme->id}/explain")->assertOk();

    $wordId = $lexeme->fresh()->lexeme_id;
    $this->actingAs($user)
        ->getJson("/api/dictionary/{$wordId}")
        ->assertOk()
        ->assertJsonPath('lexeme.explanations.0.explanation', 'A greeting.')
        ->assertJsonPath('lexeme.explanations.0.content.id', $content->id)
        ->assertJsonPath('lexeme.explanations.0.content.title', 'Test video');
});

test('explain from another content generates a separate variant', function () {
    explainAiFake();
    Queue::fake();
    Config::set('ai.explain_cache_enabled', false);
    $user = explainUser();
    $first = Content::factory()->create(['status' => 'ready', 'language' => 'en']);
    $second = Content::factory()->create(['status' => 'ready', 'language' => 'en']);
    $firstLexeme = $first->lexemes()->create(['type' => 'word', 'text' => 'hello', 'sort_order' => 1]);
    $secondLexeme = $second->lexemes()->create(['type' => 'word', 'text' => 'hello', 'sort_order' => 1]);

    // Same canonical word on both contents.
    $secondLexeme->update(['lexeme_id' => $firstLexeme->fresh()->lexeme_id]);

    $this->actingAs($user)->postJson("/api/lexemes/{$firstLexeme->id}/explain")->assertOk();
    $this->actingAs($user)->postJson("/api/lexemes/{$secondLexeme->id}/explain")->assertOk();

    Http::assertSentCount(2);
    expect(LexemeExplanation::query()->where('lexeme_id', $firstLexeme->fresh()->lexeme_id)->count())->toBe(2);

    $wordId = $firstLexeme->fresh()->lexeme_id;
    $response = $this->actingAs($user)->getJson("/api/dictionary/{$wordId}")->assertOk();
    $contents = collect($response->json('lexeme.explanations'))->pluck('content.id')->sort()->values()->all();
    expect($contents)->toBe([$first->id, $second->id]);
});

test('dictionary explain generates and stores an explanation for the word page', function () {
    explainAiFake();
    $user = explainUser();
    $word = Lexeme::query()->create([
        'slug' => 'en-hello',
        'language' => 'en',
        'lemma' => 'hello',
        'normalized_lemma' => 'hello',
    ]);

    $this->actingAs($user)
        ->postJson("/api/dictionary/{$word->id}/explain")
        ->assertOk()
        ->assertJsonPath('explanation', 'A greeting.');

    expect(LexemeExplanation::query()->where('lexeme_id', $word->id)->where('language', 'en')->value('explanation'))
        ->toBe('A greeting.');
});

test('dictionary explain returns 503 when AI feature is disabled', function () {
    Config::set('ai.enabled', false);
    $user = explainUser();
    $word = Lexeme::query()->create([
        'slug' => 'en-hello',
        'language' => 'en',
        'lemma' => 'hello',
        'normalized_lemma' => 'hello',
    ]);

    $this->actingAs($user)
        ->postJson("/api/dictionary/{$word->id}/explain")
        ->assertStatus(503);
});
