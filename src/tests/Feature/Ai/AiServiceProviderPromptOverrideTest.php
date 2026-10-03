<?php

use App\Modules\Ai\Domain\Models\AgentConversation;
use App\Modules\Ai\Domain\Models\PromptTemplate;
use App\Modules\User\Models\User;
use App\Modules\Ai\Application\Agent\ContentAgentService;
use App\Modules\Ai\Application\Agent\Data\AgentChatResponse;
use App\Contracts\Ai\AiToolCallingClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Proves AiServiceProvider::resolveBlueprintSystemPrompt() actually wires
 * a published prompt_templates override into the agent that gets
 * resolved from the container — not just that PromptRegistryService
 * resolves correctly in isolation (PromptRegistryServiceTest covers
 * that). The prompt key is derived from AgentBlueprint::name /
 * ContentAgentService::AGENT_TYPE, see that method's docblock.
 */
test('a published prompt_templates override for agent_content_authoring_system_prompt replaces the blueprint system prompt sent to the model', function () {
    $template = PromptTemplate::query()->create([
        'key' => 'agent_'.ContentAgentService::AGENT_TYPE.'_system_prompt',
        'name' => 'Content authoring agent system prompt',
    ]);
    $version = $template->versions()->create([
        'version' => 1,
        'system_template' => 'OVERRIDDEN SYSTEM PROMPT FOR TESTING',
        'user_template' => '',
    ]);
    $template->update(['active_version_id' => $version->id]);

    $user = User::factory()->create();
    $conversation = AgentConversation::query()->create(['created_by' => $user->id, 'status' => 'active']);
    $conversation->messages()->create(['role' => 'user', 'content' => 'Hello']);

    $sentMessages = null;
    $toolClient = Mockery::mock(AiToolCallingClient::class);
    $toolClient->shouldReceive('chat')->once()->andReturnUsing(function (array $messages) use (&$sentMessages) {
        $sentMessages = $messages;

        return new AgentChatResponse('ok');
    });
    app()->instance(AiToolCallingClient::class, $toolClient);

    app(ContentAgentService::class)->handleTurn($conversation->id);

    expect($sentMessages[0])->toBe(['role' => 'system', 'content' => 'OVERRIDDEN SYSTEM PROMPT FOR TESTING']);
});

test('without a published override, the agent keeps its own hardcoded system prompt', function () {
    $user = User::factory()->create();
    $conversation = AgentConversation::query()->create(['created_by' => $user->id, 'status' => 'active']);
    $conversation->messages()->create(['role' => 'user', 'content' => 'Hello']);

    $sentMessages = null;
    $toolClient = Mockery::mock(AiToolCallingClient::class);
    $toolClient->shouldReceive('chat')->once()->andReturnUsing(function (array $messages) use (&$sentMessages) {
        $sentMessages = $messages;

        return new AgentChatResponse('ok');
    });
    app()->instance(AiToolCallingClient::class, $toolClient);

    app(ContentAgentService::class)->handleTurn($conversation->id);

    expect($sentMessages[0]['content'])->toBe(ContentAgentService::blueprint()->systemPrompt);
});
