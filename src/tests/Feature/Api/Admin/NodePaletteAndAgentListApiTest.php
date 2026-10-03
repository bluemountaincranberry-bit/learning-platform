<?php

use App\Modules\Ai\Domain\Models\PromptTemplate;
use App\Modules\User\Models\User;
use App\Modules\Ai\Application\Agent\ContentAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAdminForPalette(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

test('node-palette lists every registered node_key with a label, described nodes included', function () {
    $admin = actingAdminForPalette();

    $response = $this->actingAs($admin)->getJson('/api/admin/ai-builder/node-palette')->assertOk();

    $keys = collect($response->json('data'))->pluck('node_key');
    expect($keys)->toContain('analyze', 'match', 'human_checkpoint', 'apply');

    $analyze = collect($response->json('data'))->firstWhere('node_key', 'analyze');
    expect($analyze['label'])->toBe('Analyze transcript')
        ->and($analyze['prompt_key'])->toBe('content_analysis_system_prompt');

    $match = collect($response->json('data'))->firstWhere('node_key', 'match');
    expect($match['prompt_key'])->toBeNull();
});

test('a non-admin gets 403 from node-palette', function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)->getJson('/api/admin/ai-builder/node-palette')->assertForbidden();
});

test('agents list includes every config-registered agent with its blueprint and no override by default', function () {
    $admin = actingAdminForPalette();

    $response = $this->actingAs($admin)->getJson('/api/admin/ai-builder/agents')->assertOk();

    $entry = collect($response->json('data'))->firstWhere('agent_type', ContentAgentService::AGENT_TYPE);
    expect($entry)->not->toBeNull()
        ->and($entry['has_prompt_override'])->toBeFalse()
        ->and($entry['system_prompt'])->toBe(ContentAgentService::blueprint()->systemPrompt);
});

test('agents list also includes handoff-only specialist agents, not just conversation-routable ones', function () {
    $admin = actingAdminForPalette();

    $response = $this->actingAs($admin)->getJson('/api/admin/ai-builder/agents')->assertOk();
    $types = collect($response->json('data'))->pluck('agent_type');

    expect($types)->toContain(
        \App\Modules\Ai\Application\Agent\GrammarAgentService::AGENT_TYPE,
        \App\Modules\Ai\Application\Agent\ReviewAgentService::AGENT_TYPE,
    );
});

test('agents list reports has_prompt_override true once a version is published for that agent', function () {
    $admin = actingAdminForPalette();

    $key = 'agent_'.ContentAgentService::AGENT_TYPE.'_system_prompt';
    $template = PromptTemplate::query()->create(['key' => $key, 'name' => 'Override']);
    $version = $template->versions()->create(['version' => 1, 'system_template' => 'x', 'user_template' => '']);
    $template->update(['active_version_id' => $version->id]);

    $response = $this->actingAs($admin)->getJson('/api/admin/ai-builder/agents')->assertOk();

    $entry = collect($response->json('data'))->firstWhere('agent_type', ContentAgentService::AGENT_TYPE);
    expect($entry['has_prompt_override'])->toBeTrue();
});
