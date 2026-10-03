<?php

use App\Modules\Ai\Domain\Models\PersistedGraphDefinition;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAdminForGraphBuilder(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

test('a non-admin gets 403 from the graph-definitions builder endpoints', function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)->getJson('/api/admin/ai-builder/graph-definitions')->assertForbidden();
});

test('store() saves a draft version without validating node_keys — a broken draft is allowed to be saved', function () {
    $admin = actingAdminForGraphBuilder();

    $response = $this->actingAs($admin)->postJson('/api/admin/ai-builder/graph-definitions/my_graph/versions', [
        'name' => 'My graph',
        'nodes' => [['key' => 'a', 'node' => 'not_a_real_node_type', 'x' => 10, 'y' => 20]],
        'edges' => [],
    ])->assertCreated();

    $response->assertJsonPath('data.version', 1);
    expect(PersistedGraphDefinition::query()->where('key', 'my_graph')->first()->active_version_id)->toBeNull();
});

test('publish() rejects a version whose nodes reference an unregistered node_key, and it stays unpublished', function () {
    $admin = actingAdminForGraphBuilder();

    $store = $this->actingAs($admin)->postJson('/api/admin/ai-builder/graph-definitions/broken/versions', [
        'name' => 'Broken',
        'nodes' => [['key' => 'a', 'node' => 'not_a_real_node_type']],
        'edges' => [],
    ]);

    $this->actingAs($admin)
        ->postJson("/api/admin/ai-builder/graph-definitions/broken/versions/{$store->json('data.id')}/publish")
        ->assertStatus(422);

    expect(PersistedGraphDefinition::query()->where('key', 'broken')->first()->active_version_id)->toBeNull();
});

test('publish() activates a version built from real registered node_keys', function () {
    $admin = actingAdminForGraphBuilder();

    $store = $this->actingAs($admin)->postJson('/api/admin/ai-builder/graph-definitions/valid/versions', [
        'name' => 'Valid',
        'nodes' => [
            ['key' => 'analyze', 'node' => 'analyze'],
            ['key' => 'match', 'node' => 'match'],
        ],
        'edges' => [
            ['from' => 'analyze', 'to' => 'match'],
        ],
    ]);
    $versionId = $store->json('data.id');

    $this->actingAs($admin)
        ->postJson("/api/admin/ai-builder/graph-definitions/valid/versions/{$versionId}/publish")
        ->assertOk()
        ->assertJsonPath('data.active_version_id', $versionId);
});

test('show() returns nodes/edges/layout for every saved version', function () {
    $admin = actingAdminForGraphBuilder();

    $this->actingAs($admin)->postJson('/api/admin/ai-builder/graph-definitions/g/versions', [
        'name' => 'G',
        'nodes' => [['key' => 'a', 'node' => 'analyze', 'x' => 5.5, 'y' => 12]],
        'edges' => [],
    ]);

    $show = $this->actingAs($admin)->getJson('/api/admin/ai-builder/graph-definitions/g')->assertOk();

    expect($show->json('data.versions.0.nodes.0'))->toBe(['key' => 'a', 'node' => 'analyze', 'x' => 5.5, 'y' => 12]);
});
