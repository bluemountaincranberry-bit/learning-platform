<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
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

test('explain requires auth', function () {
    $content = Content::factory()->create(['status' => 'ready']);
    $lexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'hello', 'sort_order' => 1]);

    $this->postJson("/api/lexemes/{$lexeme->id}/explain")
        ->assertUnauthorized();
});

test('explain returns 503 when AI feature is disabled', function () {
    Config::set('ai.enabled', false);
    $user = User::factory()->create();
    $user->assignRole('user');
    $content = Content::factory()->create(['status' => 'ready']);
    $lexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'hello', 'sort_order' => 1]);

    $this->actingAs($user)
        ->postJson("/api/lexemes/{$lexeme->id}/explain")
        ->assertStatus(503)
        ->assertJsonFragment(['message' => 'AI feature is disabled.']);
});

test('explain returns 404 for non-existent lexeme', function () {
    Config::set('ai.enabled', true);
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->postJson('/api/lexemes/99999/explain')
        ->assertNotFound();
});

test('explain returns 403 when content is not ready', function () {
    Config::set('ai.enabled', true);
    $user = User::factory()->create();
    $user->assignRole('user');
    $content = Content::factory()->create(['status' => 'pending']);
    $lexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'hello', 'sort_order' => 1]);

    $this->actingAs($user)
        ->postJson("/api/lexemes/{$lexeme->id}/explain")
        ->assertForbidden();
});

test('explain returns 200 and explanation when AI enabled and client responds', function () {
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

    $user = User::factory()->create();
    $user->assignRole('user');
    $content = Content::factory()->create(['status' => 'ready', 'language' => 'en']);
    $lexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'hello', 'sort_order' => 1]);

    $this->actingAs($user)
        ->postJson("/api/lexemes/{$lexeme->id}/explain")
        ->assertOk()
        ->assertJsonPath('explanation', 'A greeting.');
});

test('explain returns 503 when AI client fails', function () {
    Config::set('ai.enabled', true);
    Config::set('ai.provider', 'openai');
    Config::set('ai.openai.api_key', 'test-key');
    Http::fake([
        'api.openai.com/*' => Http::response(['error' => ['message' => 'Rate limit']], 429),
    ]);

    $user = User::factory()->create();
    $user->assignRole('user');
    $content = Content::factory()->create(['status' => 'ready']);
    $lexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'hello', 'sort_order' => 1]);

    $this->actingAs($user)
        ->postJson("/api/lexemes/{$lexeme->id}/explain")
        ->assertStatus(503)
        ->assertJsonFragment(['message' => 'AI service unavailable.']);
});

test('explain returns cached response on second request for same lexeme', function () {
    Config::set('ai.enabled', true);
    Config::set('ai.explain_cache_enabled', true);
    Config::set('ai.provider', 'openai');
    Config::set('ai.openai.api_key', 'test-key');
    Queue::fake();
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'Cached explanation.']],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    $user->assignRole('user');
    $content = Content::factory()->create(['status' => 'ready', 'language' => 'en']);
    $lexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'hello', 'sort_order' => 1]);

    $this->actingAs($user)->postJson("/api/lexemes/{$lexeme->id}/explain")->assertOk()->assertJsonPath('explanation', 'Cached explanation.');
    $this->actingAs($user)->postJson("/api/lexemes/{$lexeme->id}/explain")->assertOk()->assertJsonPath('explanation', 'Cached explanation.');

    Http::assertSentCount(1);
});

test('explain returns 429 when daily rate limit exceeded', function () {
    Config::set('ai.enabled', true);
    Config::set('ai.rate_limits.explain_per_day', 2);
    Config::set('ai.provider', 'openai');
    Config::set('ai.openai.api_key', 'test-key');
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'Explanation.']],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    $user->assignRole('user');
    $content = Content::factory()->create(['status' => 'ready']);
    $lexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'hello', 'sort_order' => 1]);

    $this->actingAs($user)->postJson("/api/lexemes/{$lexeme->id}/explain")->assertOk();
    $this->actingAs($user)->postJson("/api/lexemes/{$lexeme->id}/explain")->assertOk();
    $this->actingAs($user)
        ->postJson("/api/lexemes/{$lexeme->id}/explain")
        ->assertStatus(429)
        ->assertJsonFragment(['message' => 'Daily limit reached for this feature. Try again tomorrow.']);
});

test('explain dispatches LexemeExplanationRequested on success', function () {
    Config::set('ai.enabled', true);
    Config::set('ai.provider', 'openai');
    Config::set('ai.openai.api_key', 'test-key');
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'Ok.']],
            ],
        ], 200),
    ]);

    \Illuminate\Support\Facades\Event::fake([\App\Events\LexemeExplanationRequested::class]);

    $user = User::factory()->create();
    $user->assignRole('user');
    $content = Content::factory()->create(['status' => 'ready']);
    $lexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'hi', 'sort_order' => 1]);

    $this->actingAs($user)->postJson("/api/lexemes/{$lexeme->id}/explain")->assertOk();

    \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\LexemeExplanationRequested::class, function ($event) use ($user, $lexeme) {
        return $event->userId === $user->id && $event->lexemeId === $lexeme->id && $event->source === 'study_screen';
    });
});
