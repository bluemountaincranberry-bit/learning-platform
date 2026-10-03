<?php

use App\Modules\Ai\Domain\Models\PromptTemplate;
use App\Modules\User\Models\User;
use App\Modules\Ai\Application\Agent\ContentAgentService;
use App\Modules\Ai\Application\Agent\GrammarAgentService;
use App\Modules\Ai\Application\Agent\StudentTutorAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAdminForCatalog(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

test('a non-admin gets 403 from prompt-catalog', function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)->getJson('/api/admin/ai-builder/prompt-catalog')->assertForbidden();
});

test('catalog is grouped into the four real-flow buckets', function () {
    $admin = actingAdminForCatalog();

    $response = $this->actingAs($admin)->getJson('/api/admin/ai-builder/prompt-catalog')->assertOk();

    $flows = collect($response->json('data'))->pluck('flow')->all();
    expect($flows)->toBe(['agent_chat', 'content_ingestion', 'student_practice', 'builder_tools']);
});

test('builder_tools carries the prompt-improvement assistant\'s own key with a real code default', function () {
    $admin = actingAdminForCatalog();

    $response = $this->actingAs($admin)->getJson('/api/admin/ai-builder/prompt-catalog')->assertOk();

    $entry = collect($response->json('data'))->firstWhere('flow', 'builder_tools')['entries'][0];
    expect($entry['key'])->toBe('prompt_improvement_assistant_system_prompt')
        ->and($entry['kind'])->toBe('prompt')
        ->and($entry['code_default_preview'])->toBeString()->not->toBeEmpty();
});

test('agent_chat includes both conversation-routable and specialist-only agents, with a real code-default preview', function () {
    $admin = actingAdminForCatalog();

    $response = $this->actingAs($admin)->getJson('/api/admin/ai-builder/prompt-catalog')->assertOk();
    $agentChat = collect($response->json('data'))->firstWhere('flow', 'agent_chat');
    $keys = collect($agentChat['entries'])->pluck('key');

    expect($keys)->toContain(
        'agent_'.ContentAgentService::AGENT_TYPE.'_system_prompt',
        'agent_'.GrammarAgentService::AGENT_TYPE.'_system_prompt',
        'chat_context_system_prompt',
    );

    $contentAgentEntry = collect($agentChat['entries'])->firstWhere('key', 'agent_'.ContentAgentService::AGENT_TYPE.'_system_prompt');
    expect($contentAgentEntry['kind'])->toBe('agent')
        ->and($contentAgentEntry['code_default_preview'])->not->toBeEmpty()
        ->and($contentAgentEntry['code_default_preview'])->toBe(\Illuminate\Support\Str::limit(ContentAgentService::blueprint()->systemPrompt, 160));
});

test('content_ingestion includes the real production content-analysis key, shared with the graph canvas Analyze node', function () {
    $admin = actingAdminForCatalog();

    $response = $this->actingAs($admin)->getJson('/api/admin/ai-builder/prompt-catalog')->assertOk();
    $ingestion = collect($response->json('data'))->firstWhere('flow', 'content_ingestion');

    expect(collect($ingestion['entries'])->pluck('key'))->toContain('content_analysis_system_prompt');
});

test('a plain (non-agent) prompt entry has no code_default_preview', function () {
    $admin = actingAdminForCatalog();

    $response = $this->actingAs($admin)->getJson('/api/admin/ai-builder/prompt-catalog')->assertOk();
    $ingestion = collect($response->json('data'))->firstWhere('flow', 'content_ingestion');
    $entry = collect($ingestion['entries'])->firstWhere('key', 'content_analysis_system_prompt');

    expect($entry['kind'])->toBe('prompt')
        ->and($entry['code_default_preview'])->toBeNull();
});

test('has_override reflects a published prompt_templates row for that exact key', function () {
    $admin = actingAdminForCatalog();

    $template = PromptTemplate::query()->create(['key' => 'content_analysis_system_prompt', 'name' => 'x']);
    $version = $template->versions()->create(['version' => 1, 'system_template' => 'sys', 'user_template' => 'usr']);
    $template->update(['active_version_id' => $version->id]);

    $response = $this->actingAs($admin)->getJson('/api/admin/ai-builder/prompt-catalog')->assertOk();
    $ingestion = collect($response->json('data'))->firstWhere('flow', 'content_ingestion');
    $entry = collect($ingestion['entries'])->firstWhere('key', 'content_analysis_system_prompt');

    expect($entry['has_override'])->toBeTrue();

    $other = collect($ingestion['entries'])->firstWhere('key', 'lesson_analysis_system_prompt');
    expect($other['has_override'])->toBeFalse();
});

test('an agent entry lists its real tools with name/description/side_effect, resolved from the actual blueprint', function () {
    $admin = actingAdminForCatalog();

    $response = $this->actingAs($admin)->getJson('/api/admin/ai-builder/prompt-catalog')->assertOk();
    $agentChat = collect($response->json('data'))->firstWhere('flow', 'agent_chat');
    $tutorEntry = collect($agentChat['entries'])->firstWhere('key', 'agent_'.StudentTutorAgentService::AGENT_TYPE.'_system_prompt');

    expect($tutorEntry['tools'])->not->toBeEmpty();

    $toolNames = collect($tutorEntry['tools'])->pluck('name');
    expect($toolNames)->toContain('get_user_mistakes');

    $mistakesTool = collect($tutorEntry['tools'])->firstWhere('name', 'get_user_mistakes');
    expect($mistakesTool['description'])->not->toBeEmpty()
        ->and($mistakesTool['side_effect'])->toBe('read_only');
});

test('a plain (non-agent) prompt entry has an empty tools list', function () {
    $admin = actingAdminForCatalog();

    $response = $this->actingAs($admin)->getJson('/api/admin/ai-builder/prompt-catalog')->assertOk();
    $ingestion = collect($response->json('data'))->firstWhere('flow', 'content_ingestion');
    $entry = collect($ingestion['entries'])->firstWhere('key', 'content_analysis_system_prompt');

    expect($entry['tools'])->toBe([]);
});
