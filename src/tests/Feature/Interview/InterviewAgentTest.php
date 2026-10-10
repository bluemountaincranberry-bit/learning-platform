<?php

use App\Contracts\Ai\AiToolCallingClient;
use App\Modules\Ai\Application\Agent\Data\AgentChatResponse;
use App\Modules\Ai\Application\Agent\Data\AgentToolCall;
use App\Modules\Ai\Application\Agent\InterviewAgentService;
use App\Modules\Ai\Domain\Models\AgentConversation;
use App\Modules\Ai\Interfaces\Jobs\RunAgentTurnJob;
use App\Modules\Interview\Domain\Models\InterviewPracticeSession;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Interview Agent receives only confirmed context for its private coached session through AgentLoop', function () {
    $learner = User::factory()->create();
    $profile = test()->actingAs($learner)->putJson('/api/interview/profile', [
        'career_goal' => 'Junior API developer', 'experience_stories' => ['I built a study project.'],
    ])->assertOk();
    $question = test()->postJson('/api/interview/questions', [
        'prompt_en' => 'Describe an API you built.', 'prompt_ru' => 'Опишите API, который вы создали.',
    ])->assertCreated()->json('data');
    $session = test()->postJson('/api/interview/sessions', [
        'mode' => 'coached', 'question_ids' => [$question['id']],
    ])->assertCreated()->json('data');
    $conversation = AgentConversation::query()->findOrFail($session['conversation_id']);
    $conversation->messages()->create(['role' => 'user', 'content' => 'I built a small API for my study project.']);

    $toolClient = Mockery::mock(AiToolCallingClient::class);
    $toolClient->shouldReceive('chat')->twice()->andReturn(
        new AgentChatResponse(null, [new AgentToolCall('context_1', 'get_interview_practice_context', [])]),
        new AgentChatResponse('Tell me what problem your API solved.'),
    );
    app()->instance(AiToolCallingClient::class, $toolClient);

    (new RunAgentTurnJob($conversation->id))->handle();

    $toolMessage = $conversation->messages()->where('role', 'tool')->firstOrFail();
    expect($toolMessage->tool_result['mode'])->toBe('coached')
        ->and($toolMessage->tool_result['profile']['career_goal'])->toBe('Junior API developer')
        ->and($toolMessage->tool_result['questions'][0]['prompt_en'])->toBe('Describe an API you built.')
        ->and($conversation->messages()->where('role', 'assistant')->first()->content)->toBe('Tell me what problem your API solved.')
        ->and(config('ai.agent.registry')[InterviewAgentService::AGENT_TYPE])->toBe(InterviewAgentService::class)
        ->and(InterviewPracticeSession::query()->where('id', $session['id'])->exists())->toBeTrue();
});

test('Interview Agent stores an AI question proposal as pending until learner confirms it', function () {
    $learner = User::factory()->create();
    $session = test()->actingAs($learner)->postJson('/api/interview/sessions', ['mode' => 'coached'])->assertCreated()->json('data');
    $conversation = AgentConversation::query()->findOrFail($session['conversation_id']);
    $conversation->messages()->create(['role' => 'user', 'content' => 'Suggest an interview question about API timeouts.']);

    $toolClient = Mockery::mock(AiToolCallingClient::class);
    $toolClient->shouldReceive('chat')->times(3)->andReturn(
        new AgentChatResponse(null, [new AgentToolCall('context_1', 'get_interview_practice_context', [])]),
        new AgentChatResponse(null, [new AgentToolCall('draft_1', 'propose_interview_question', [
            'prompt_en' => 'How do you handle an API timeout?', 'prompt_ru' => 'Как вы обрабатываете таймаут API?',
        ])]),
        new AgentChatResponse('I drafted a bilingual question for your review.'),
    );
    app()->instance(AiToolCallingClient::class, $toolClient);

    (new RunAgentTurnJob($conversation->id))->handle();

    test()->assertDatabaseHas('interview_ai_drafts', ['user_id' => $learner->id, 'kind' => 'question', 'status' => 'pending']);
    test()->getJson('/api/interview/questions')->assertOk()->assertJsonCount(0, 'data');
    test()->getJson('/api/interview/drafts')->assertOk()->assertJsonPath('data.0.payload.prompt_en', 'How do you handle an API timeout?');
    $draftId = test()->getJson('/api/interview/drafts')->json('data.0.id');
    test()->postJson('/api/interview/drafts/'.$draftId.'/confirm')->assertOk();
    test()->getJson('/api/interview/questions')->assertOk()->assertJsonPath('data.0.prompt_en', 'How do you handle an API timeout?');
});
