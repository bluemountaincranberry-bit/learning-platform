<?php

use App\Modules\Ai\Domain\Models\PromptTemplate;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAdminForBuilder(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

test('a non-admin gets 403 from every prompt-templates builder endpoint', function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)->getJson('/api/admin/ai-builder/prompt-templates')->assertForbidden();
    $this->actingAs($user)->postJson('/api/admin/ai-builder/prompt-templates/foo/versions', [
        'name' => 'x', 'system_template' => 's', 'user_template' => 'u',
    ])->assertForbidden();
});

test('store() creates a template and a first draft version, not yet active', function () {
    $admin = actingAdminForBuilder();

    $response = $this->actingAs($admin)->postJson('/api/admin/ai-builder/prompt-templates/ai_explain_lexeme/versions', [
        'name' => 'Explain lexeme',
        'system_template' => 'You are a tutor for {{language}}.',
        'user_template' => 'Explain {{lexeme}}.',
    ])->assertCreated();

    $response->assertJsonPath('data.version', 1);

    $template = PromptTemplate::query()->where('key', 'ai_explain_lexeme')->firstOrFail();
    expect($template->active_version_id)->toBeNull();
});

test('store() normalizes a null user template for agent prompts', function () {
    $admin = actingAdminForBuilder();

    $response = $this->actingAs($admin)->postJson('/api/admin/ai-builder/prompt-templates/agent_chat/versions', [
        'name' => 'Agent chat',
        'system_template' => 'You are a helpful tutor.',
        'user_template' => null,
    ])->assertCreated();

    expect($response->json('data.user_template'))->toBe('');
});

test('a second store() call appends version 2 rather than overwriting version 1', function () {
    $admin = actingAdminForBuilder();

    $this->actingAs($admin)->postJson('/api/admin/ai-builder/prompt-templates/k/versions', [
        'name' => 'K', 'system_template' => 'v1 sys', 'user_template' => 'v1 usr',
    ])->assertCreated();

    $second = $this->actingAs($admin)->postJson('/api/admin/ai-builder/prompt-templates/k/versions', [
        'name' => 'K', 'system_template' => 'v2 sys', 'user_template' => 'v2 usr',
    ])->assertCreated();

    $second->assertJsonPath('data.version', 2);

    $show = $this->actingAs($admin)->getJson('/api/admin/ai-builder/prompt-templates/k')->assertOk();
    expect($show->json('data.versions'))->toHaveCount(2);
});

test('publish() activates the given version and it is reflected in index()/show()', function () {
    $admin = actingAdminForBuilder();

    $store = $this->actingAs($admin)->postJson('/api/admin/ai-builder/prompt-templates/k/versions', [
        'name' => 'K', 'system_template' => 'sys', 'user_template' => 'usr',
    ])->assertCreated();
    $versionId = $store->json('data.id');

    $this->actingAs($admin)->postJson("/api/admin/ai-builder/prompt-templates/k/versions/{$versionId}/publish")
        ->assertOk()
        ->assertJsonPath('data.active_version_id', $versionId);

    $index = $this->actingAs($admin)->getJson('/api/admin/ai-builder/prompt-templates')->assertOk();
    expect(collect($index->json('data'))->firstWhere('key', 'k')['active_version_id'])->toBe($versionId);
});

test('publish() with a version id belonging to a different template returns 422', function () {
    $admin = actingAdminForBuilder();

    $storeA = $this->actingAs($admin)->postJson('/api/admin/ai-builder/prompt-templates/a/versions', [
        'name' => 'A', 'system_template' => 's', 'user_template' => 'u',
    ]);
    $this->actingAs($admin)->postJson('/api/admin/ai-builder/prompt-templates/b/versions', [
        'name' => 'B', 'system_template' => 's', 'user_template' => 'u',
    ]);

    $this->actingAs($admin)
        ->postJson("/api/admin/ai-builder/prompt-templates/b/versions/{$storeA->json('data.id')}/publish")
        ->assertStatus(422);
});

test('test-run renders the templates and returns the real response, without saving anything', function () {
    $admin = actingAdminForBuilder();
    config(['ai.provider' => 'openai', 'ai.openai.api_key' => 'test-key']);
    Illuminate\Support\Facades\Http::fake([
        'api.openai.com/*' => Illuminate\Support\Facades\Http::response(['choices' => [['message' => ['content' => 'Test reply.']]]], 200),
    ]);

    $response = $this->actingAs($admin)->postJson('/api/admin/ai-builder/prompt-templates/ai_explain_lexeme/test', [
        'system_template' => 'You are a tutor for {{language}}.',
        'user_template' => 'Explain {{lexeme}}.',
        'variables' => ['language' => 'English', 'lexeme' => 'run'],
    ])->assertOk();

    expect($response->json('data.rendered_system'))->toBe('You are a tutor for English.')
        ->and($response->json('data.response'))->toBe('Test reply.');

    expect(PromptTemplate::query()->count())->toBe(0);
});

test('test-run returns 422 when a template placeholder has no matching variable', function () {
    $admin = actingAdminForBuilder();

    $this->actingAs($admin)->postJson('/api/admin/ai-builder/prompt-templates/k/test', [
        'system_template' => 'Hello {{name}}.',
        'user_template' => 'x',
    ])->assertStatus(422);
});

test('a non-admin gets 403 from test-run', function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)->postJson('/api/admin/ai-builder/prompt-templates/k/test', [
        'system_template' => 's', 'user_template' => 'u',
    ])->assertForbidden();
});

test('show() for a known agent key with no draft yet returns the code default and tool list instead of 404', function () {
    $admin = actingAdminForBuilder();

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/ai-builder/prompt-templates/agent_student_tutor_system_prompt')
        ->assertOk();

    expect($response->json('data.kind'))->toBe('agent')
        ->and($response->json('data.active_version_id'))->toBeNull()
        ->and($response->json('data.versions'))->toBe([])
        ->and($response->json('data.code_default_system'))->toBeString()->not->toBeEmpty()
        ->and($response->json('data.tools'))->not->toBeEmpty();
});

test('show() for a known plain-prompt key with no draft yet returns 200 with a null code default, not a fake one', function () {
    $admin = actingAdminForBuilder();

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/ai-builder/prompt-templates/ai_explain_lexeme')
        ->assertOk();

    expect($response->json('data.kind'))->toBe('prompt')
        ->and($response->json('data.code_default_system'))->toBeNull()
        ->and($response->json('data.tools'))->toBe([]);
});

test('show() for a key that is neither an existing draft nor a known catalog entry still 404s', function () {
    $admin = actingAdminForBuilder();

    $this->actingAs($admin)
        ->getJson('/api/admin/ai-builder/prompt-templates/totally_unknown_key')
        ->assertNotFound();
});

test('chat() replies about the prompt and surfaces a proposed rewrite without saving anything', function () {
    $admin = actingAdminForBuilder();
    config(['ai.provider' => 'openai', 'ai.openai.api_key' => 'test-key']);
    Illuminate\Support\Facades\Http::fake([
        'api.openai.com/*' => Illuminate\Support\Facades\Http::response([
            'choices' => [[
                'message' => ['content' => json_encode([
                    'reply' => 'Вот более короткий вариант.',
                    'proposed_system_template' => 'Shorter system prompt.',
                    'proposed_user_template' => null,
                ])],
            ]],
        ], 200),
    ]);

    $response = $this->actingAs($admin)->postJson('/api/admin/ai-builder/prompt-templates/k/chat', [
        'system_template' => 'A very long and verbose system prompt.',
        'user_template' => 'u',
        'message' => 'Сделай короче',
    ])->assertOk();

    expect($response->json('data.reply'))->toBe('Вот более короткий вариант.')
        ->and($response->json('data.proposed_system_template'))->toBe('Shorter system prompt.')
        ->and($response->json('data.proposed_user_template'))->toBeNull();

    expect(PromptTemplate::query()->count())->toBe(0);
});

test('a non-admin gets 403 from chat', function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)->postJson('/api/admin/ai-builder/prompt-templates/k/chat', [
        'system_template' => 's', 'user_template' => 'u', 'message' => 'hi',
    ])->assertForbidden();
});
