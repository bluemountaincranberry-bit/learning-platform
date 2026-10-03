<?php

use App\Modules\Ai\Interfaces\Jobs\RunAgentTurnJob;
use App\Modules\Ai\Domain\Models\AgentConversation;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\User\Models\User;
use App\Modules\Ai\Application\Agent\ContentAgentService;
use App\Modules\Ai\Application\Agent\Data\AgentChatResponse;
use App\Modules\Ai\Application\Agent\Data\AgentToolCall;
use App\Modules\Ai\Application\Agent\StudentTutorAgentService;
use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\AiToolCallingClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('job resolves the agent registered for the conversation agent_type and runs a turn', function () {
    $user = User::factory()->create();
    $conversation = AgentConversation::query()->create([
        'created_by' => $user->id,
        'status' => 'active',
        'agent_type' => ContentAgentService::AGENT_TYPE,
    ]);
    $conversation->messages()->create(['role' => 'user', 'content' => 'What can you help me with?']);

    $toolClient = Mockery::mock(AiToolCallingClient::class);
    $toolClient->shouldReceive('chat')->once()->andReturn(new AgentChatResponse('I can help with that.'));
    app()->instance(AiToolCallingClient::class, $toolClient);

    (new RunAgentTurnJob($conversation->id))->handle();

    $assistantMessages = $conversation->messages()->where('role', 'assistant')->get();
    expect($assistantMessages)->toHaveCount(1)
        ->and($assistantMessages->first()->content)->toBe('I can help with that.');
});

test('job falls back to a generic error message when agent_type has no registered agent', function () {
    $user = User::factory()->create();
    $conversation = AgentConversation::query()->create([
        'created_by' => $user->id,
        'status' => 'active',
        'agent_type' => 'not_a_registered_agent',
    ]);
    $conversation->messages()->create(['role' => 'user', 'content' => 'Hello?']);

    (new RunAgentTurnJob($conversation->id))->handle();

    $assistantMessages = $conversation->messages()->where('role', 'assistant')->get();
    expect($assistantMessages)->toHaveCount(1)
        ->and($assistantMessages->first()->content)->toContain('Something went wrong');
});

test('job resolves StudentTutorAgentService for a student_tutor conversation — proof the registry is not hardcoded to one agent', function () {
    $user = User::factory()->create();
    $conversation = AgentConversation::query()->create([
        'created_by' => $user->id,
        'status' => 'active',
        'agent_type' => StudentTutorAgentService::AGENT_TYPE,
    ]);
    $conversation->messages()->create(['role' => 'user', 'content' => 'Hi!']);

    $toolClient = Mockery::mock(AiToolCallingClient::class);
    $toolClient->shouldReceive('chat')->once()->andReturn(new AgentChatResponse('Hello there!'));
    app()->instance(AiToolCallingClient::class, $toolClient);

    (new RunAgentTurnJob($conversation->id))->handle();

    $assistantMessages = $conversation->messages()->where('role', 'assistant')->get();
    expect($assistantMessages)->toHaveCount(1)
        ->and($assistantMessages->first()->content)->toBe('Hello there!');
});

test('agent_type defaults to content_authoring for a row created without it', function () {
    $user = User::factory()->create();

    $conversation = AgentConversation::query()->create(['created_by' => $user->id, 'status' => 'active']);

    expect($conversation->fresh()->agent_type)->toBe(ContentAgentService::AGENT_TYPE);
});
