<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Ai\Domain\Models\PersistedGraphDefinition;
use App\Modules\User\Models\User;
use App\Contracts\Ai\AiJsonClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAdminForGraphTestRun(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

test('test-run executes the draft version and returns candidates without persisting anything', function () {
    $admin = actingAdminForGraphTestRun();

    $record = PersistedGraphDefinition::query()->create(['key' => 'ai_analysis', 'name' => 'AI analysis']);
    $version = $record->versions()->create([
        'version' => 1,
        'nodes' => [['key' => 'analyze', 'node' => 'analyze'], ['key' => 'match', 'node' => 'match']],
        'edges' => [['from' => 'analyze', 'to' => 'match']],
    ]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [['text' => 'get up', 'type' => 'phrasal_verb', 'translation' => 'вставать']],
        'grammar' => [],
    ]);
    app()->instance(AiJsonClient::class, $client);

    $response = $this->actingAs($admin)->postJson(
        "/api/admin/ai-builder/graph-definitions/ai_analysis/versions/{$version->id}/test",
        ['test_transcript' => 'I need to get up early.']
    )->assertOk();

    expect($response->json('data.status'))->toBe('completed')
        ->and($response->json('data.lexeme_candidates.0.text'))->toBe('get up')
        ->and(Content::query()->count())->toBe(0);
});

test('test-run returns 422 for a version whose nodes reference an unregistered node_key', function () {
    $admin = actingAdminForGraphTestRun();

    $record = PersistedGraphDefinition::query()->create(['key' => 'ai_analysis', 'name' => 'AI analysis']);
    $version = $record->versions()->create([
        'version' => 1,
        'nodes' => [['key' => 'a', 'node' => 'not_a_real_node_type']],
        'edges' => [],
    ]);

    $this->actingAs($admin)->postJson(
        "/api/admin/ai-builder/graph-definitions/ai_analysis/versions/{$version->id}/test",
        ['test_transcript' => 'x']
    )->assertStatus(422);
});

test('test_transcript is required', function () {
    $admin = actingAdminForGraphTestRun();

    $record = PersistedGraphDefinition::query()->create(['key' => 'ai_analysis', 'name' => 'AI analysis']);
    $version = $record->versions()->create(['version' => 1, 'nodes' => [['key' => 'a', 'node' => 'analyze']], 'edges' => []]);

    $this->actingAs($admin)->postJson(
        "/api/admin/ai-builder/graph-definitions/ai_analysis/versions/{$version->id}/test",
        []
    )->assertStatus(422);
});

test('a non-admin gets 403 from graph test-run', function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('user');

    $record = PersistedGraphDefinition::query()->create(['key' => 'ai_analysis', 'name' => 'AI analysis']);
    $version = $record->versions()->create(['version' => 1, 'nodes' => [['key' => 'a', 'node' => 'analyze']], 'edges' => []]);

    $this->actingAs($user)->postJson(
        "/api/admin/ai-builder/graph-definitions/ai_analysis/versions/{$version->id}/test",
        ['test_transcript' => 'x']
    )->assertForbidden();
});
