<?php

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

function translateAiFake(): void
{
    Config::set('ai.enabled', true);
    Config::set('ai.provider', 'openai');
    Config::set('ai.openai.api_key', 'test-key');
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'Привет.']],
            ],
        ], 200),
    ]);
}

test('translate requires auth', function () {
    $this->postJson('/api/ai/translate', ['text' => 'Hello.', 'target_language' => 'ru'])
        ->assertUnauthorized();
});

test('translate returns 503 when AI feature is disabled', function () {
    Config::set('ai.enabled', false);
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->postJson('/api/ai/translate', ['text' => 'Hello.', 'target_language' => 'ru'])
        ->assertStatus(503);
});

test('translate validates input', function () {
    translateAiFake();
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->postJson('/api/ai/translate', ['text' => 'Hello.', 'target_language' => 'russian'])
        ->assertStatus(422);

    $this->actingAs($user)
        ->postJson('/api/ai/translate', ['target_language' => 'ru'])
        ->assertStatus(422);
});

test('translate returns the translation and caches it', function () {
    translateAiFake();
    $user = User::factory()->create();
    $user->assignRole('user');

    $payload = ['text' => 'Hello.', 'target_language' => 'ru'];
    $this->actingAs($user)->postJson('/api/ai/translate', $payload)->assertOk()->assertJsonPath('translation', 'Привет.');
    $this->actingAs($user)->postJson('/api/ai/translate', $payload)->assertOk()->assertJsonPath('translation', 'Привет.');

    Http::assertSentCount(1);
});

test('translate returns 503 when AI client fails', function () {
    Config::set('ai.enabled', true);
    Config::set('ai.provider', 'openai');
    Config::set('ai.openai.api_key', 'test-key');
    Http::fake([
        'api.openai.com/*' => Http::response(['error' => ['message' => 'Rate limit']], 429),
    ]);
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->postJson('/api/ai/translate', ['text' => 'Hello.', 'target_language' => 'ru'])
        ->assertStatus(503);
});
