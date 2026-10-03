<?php

use App\Modules\Ai\Domain\Models\AiConversation;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

test('create conversation requires auth', function () {
    $this->postJson('/api/ai/conversations')
        ->assertUnauthorized();
});

test('create conversation returns 503 when AI disabled', function () {
    Config::set('ai.enabled', false);
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->postJson('/api/ai/conversations')
        ->assertStatus(503)
        ->assertJsonFragment(['message' => 'AI feature is disabled.']);
});

test('create conversation returns 201 and conversation_id when AI enabled', function () {
    Config::set('ai.enabled', true);
    $user = User::factory()->create();
    $user->assignRole('user');

    $response = $this->actingAs($user)
        ->postJson('/api/ai/conversations')
        ->assertStatus(201)
        ->assertJsonStructure(['conversation_id']);

    $id = $response->json('conversation_id');
    $this->assertDatabaseHas('ai_conversations', ['id' => $id, 'user_id' => $user->id]);
});

test('send message requires auth', function () {
    $conv = AiConversation::factory()->create();
    $this->postJson("/api/ai/conversations/{$conv->id}/messages", ['content' => 'Hello'])
        ->assertUnauthorized();
});

test('send message returns 503 when AI disabled', function () {
    Config::set('ai.enabled', false);
    $user = User::factory()->create();
    $user->assignRole('user');
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson("/api/ai/conversations/{$conv->id}/messages", ['content' => 'Hello'])
        ->assertStatus(503);
});

test('send message returns 404 when conversation not owned by user', function () {
    Config::set('ai.enabled', true);
    $user = User::factory()->create();
    $user->assignRole('user');
    $other = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user)
        ->postJson("/api/ai/conversations/{$conv->id}/messages", ['content' => 'Hello'])
        ->assertNotFound();
});

test('send message validates content required', function () {
    Config::set('ai.enabled', true);
    $user = User::factory()->create();
    $user->assignRole('user');
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson("/api/ai/conversations/{$conv->id}/messages", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content']);
});

test('send message creates user and assistant messages and returns reply', function () {
    Config::set('ai.enabled', true);
    Config::set('ai.provider', 'openai');
    Config::set('ai.openai.api_key', 'test-key');
    // This test is about message persistence/response shape, not semantic
    // caching (task 5.4, covered by its own SemanticCacheServiceTest) — off
    // here so the single faked completions response isn't also consumed by
    // the cache's embeddings lookup.
    Config::set('ai.semantic_cache.enabled', false);
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'Hi back!']],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    $user->assignRole('user');
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)
        ->postJson("/api/ai/conversations/{$conv->id}/messages", ['content' => 'Hello bot'])
        ->assertOk()
        ->assertJsonStructure(['reply', 'message_id'])
        ->assertJsonFragment(['reply' => 'Hi back!']);

    $this->assertDatabaseHas('ai_messages', [
        'conversation_id' => $conv->id,
        'role' => 'user',
        'content' => 'Hello bot',
    ]);
    $this->assertDatabaseHas('ai_messages', [
        'conversation_id' => $conv->id,
        'role' => 'assistant',
        'content' => 'Hi back!',
    ]);
});

test('integration: create conversation, send two messages, assert replies and messages in DB', function () {
    Config::set('ai.enabled', true);
    Config::set('ai.provider', 'openai');
    Config::set('ai.openai.api_key', 'test-key');
    // See the previous test's comment — the faked HTTP sequence here models
    // two completion calls only, not the extra embeddings call the
    // semantic cache (task 5.4) would otherwise make on the first,
    // history-less message.
    Config::set('ai.semantic_cache.enabled', false);
    Http::fake([
        'api.openai.com/*' => Http::sequence()
            ->push([
                'choices' => [
                    ['message' => ['content' => 'First reply.']],
                ],
            ], 200)
            ->push([
                'choices' => [
                    ['message' => ['content' => 'Second reply.']],
                ],
            ], 200),
    ]);

    $user = User::factory()->create();
    $user->assignRole('user');

    $createRes = $this->actingAs($user)
        ->postJson('/api/ai/conversations')
        ->assertStatus(201);
    $conversationId = $createRes->json('conversation_id');

    $firstRes = $this->actingAs($user)
        ->postJson("/api/ai/conversations/{$conversationId}/messages", ['content' => 'First message'])
        ->assertOk()
        ->assertJsonFragment(['reply' => 'First reply.']);

    $this->assertDatabaseCount('ai_messages', 2);
    $this->assertDatabaseHas('ai_messages', [
        'conversation_id' => $conversationId,
        'role' => 'user',
        'content' => 'First message',
    ]);
    $this->assertDatabaseHas('ai_messages', [
        'conversation_id' => $conversationId,
        'role' => 'assistant',
        'content' => 'First reply.',
    ]);

    $this->actingAs($user)
        ->postJson("/api/ai/conversations/{$conversationId}/messages", ['content' => 'Second message'])
        ->assertOk()
        ->assertJsonFragment(['reply' => 'Second reply.']);

    $this->assertDatabaseCount('ai_messages', 4);
    $this->assertDatabaseHas('ai_messages', [
        'conversation_id' => $conversationId,
        'role' => 'user',
        'content' => 'Second message',
    ]);
    $this->assertDatabaseHas('ai_messages', [
        'conversation_id' => $conversationId,
        'role' => 'assistant',
        'content' => 'Second reply.',
    ]);
});
