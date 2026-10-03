<?php

use App\Modules\Ai\Application\Agent\Data\AgentChatResponse;
use App\Modules\Ai\Application\Agent\Data\AgentToolCall;
use App\Modules\Ai\Application\Agent\StudentTutorAgentService;
use App\Contracts\Ai\AiToolCallingClient;
use App\Modules\Ai\Domain\Models\AgentConversation;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Task 1.10 proof: a second agent, built purely from the runtime this epic
 * introduced (AgentLoop, AgentBlueprint, sideEffect wiring check, tracing,
 * agent_type registry) — no engine changes needed for it to work.
 */
test('student tutor answers a level question through one tool call', function () {
    $user = User::factory()->create();
    $conversation = AgentConversation::query()->create([
        'created_by' => $user->id,
        'status' => 'active',
        'agent_type' => StudentTutorAgentService::AGENT_TYPE,
    ]);
    $conversation->messages()->create(['role' => 'user', 'content' => 'What level am I at?']);

    $content = Content::factory()->create();
    $lexeme = Lexeme::query()->create([
        'slug' => 'en-gate', 'language' => 'en', 'lemma' => 'gate', 'normalized_lemma' => 'gate',
        'status' => 'published', 'level' => 'B1',
    ]);
    $contentLexeme = $content->lexemes()->create([
        'type' => ContentLexeme::TYPE_WORD, 'text' => 'gate', 'sort_order' => 1, 'lexeme_id' => $lexeme->id,
    ]);
    UserLexemeProgress::query()->create(['user_id' => $user->id, 'content_lexeme_id' => $contentLexeme->id, 'lexeme_id' => $lexeme->id, 'learned_at' => now()]);

    $callCount = 0;
    $toolClient = Mockery::mock(AiToolCallingClient::class);
    $toolClient->shouldReceive('chat')->twice()->andReturnUsing(function () use (&$callCount) {
        $callCount++;

        if ($callCount === 1) {
            return new AgentChatResponse(null, [new AgentToolCall('call_1', 'get_user_level', [])]);
        }

        return new AgentChatResponse('Based on your vocabulary, you look to be around B1.');
    });
    app()->instance(AiToolCallingClient::class, $toolClient);

    app(StudentTutorAgentService::class)->handleTurn($conversation->id);

    $toolMessages = $conversation->messages()->where('role', 'tool')->get();
    expect($toolMessages)->toHaveCount(1)
        ->and($toolMessages->first()->tool_name)->toBe('get_user_level')
        ->and($toolMessages->first()->tool_result['estimated_level'])->toBe('B1');

    $assistantMessages = $conversation->messages()->where('role', 'assistant')->get();
    expect($assistantMessages)->toHaveCount(1)
        ->and($assistantMessages->first()->content)->toContain('B1');
});

test('student tutor replies directly when no tool call is needed', function () {
    $user = User::factory()->create();
    $conversation = AgentConversation::query()->create([
        'created_by' => $user->id,
        'status' => 'active',
        'agent_type' => StudentTutorAgentService::AGENT_TYPE,
    ]);
    $conversation->messages()->create(['role' => 'user', 'content' => 'Hi!']);

    $toolClient = Mockery::mock(AiToolCallingClient::class);
    $toolClient->shouldReceive('chat')->once()->andReturn(new AgentChatResponse('Hello! How can I help you study today?'));
    app()->instance(AiToolCallingClient::class, $toolClient);

    app(StudentTutorAgentService::class)->handleTurn($conversation->id);

    $assistantMessages = $conversation->messages()->where('role', 'assistant')->get();
    expect($assistantMessages)->toHaveCount(1)
        ->and($assistantMessages->first()->content)->toBe('Hello! How can I help you study today?');
});

/**
 * Task 4.3/4.4: proves the epic's actual Definition of Done, not just that
 * HandoffTool works in isolation — TutorAgent (StudentTutorAgentService)
 * really can hand off to GrammarAgentService through its own model-directed
 * tool-calling loop, wired via AiServiceProvider::bindHandoffTool().
 */
test('student tutor hands off a grammar question to the grammar specialist and relays its reply', function () {
    $user = User::factory()->create();
    $conversation = AgentConversation::query()->create([
        'created_by' => $user->id,
        'status' => 'active',
        'agent_type' => StudentTutorAgentService::AGENT_TYPE,
    ]);
    $conversation->messages()->create(['role' => 'user', 'content' => 'What is wrong with "I finished my homework already"?']);

    $callCount = 0;
    $toolClient = Mockery::mock(AiToolCallingClient::class);
    // Shared by both the outer TutorAgent loop and GrammarAgentService's
    // own nested AgentLoop (HandoffTool runs it synchronously, in-process)
    // — same AiToolCallingClient binding, so both loops' chat() calls land
    // on this one mock and share the call counter.
    $toolClient->shouldReceive('chat')->times(3)->andReturnUsing(function ($messages) use (&$callCount) {
        $callCount++;

        if ($callCount === 1) {
            // Outer TutorAgent loop decides to hand off.
            return new AgentChatResponse(null, [
                new AgentToolCall('call_1', 'handoff_to_grammar_specialist', [
                    'task' => 'Student wrote: "I finished my homework already." What is wrong?',
                ]),
            ]);
        }

        if ($callCount === 2) {
            // GrammarAgentService's own nested loop, first (and only) call:
            // system prompt identifies it as the grammar specialist.
            $systemMessage = collect($messages)->firstWhere('role', 'system')['content'] ?? '';
            expect($systemMessage)->toContain('grammar specialist');

            return new AgentChatResponse('Use the present perfect: "I have already finished my homework."');
        }

        // Outer TutorAgent loop's final reply, now with the handoff's tool
        // result (the specialist's text) available in its own message history.
        return new AgentChatResponse('Use the present perfect: "I have already finished my homework."');
    });
    app()->instance(AiToolCallingClient::class, $toolClient);

    app(StudentTutorAgentService::class)->handleTurn($conversation->id);

    $toolMessages = $conversation->messages()->where('role', 'tool')->get();
    expect($toolMessages)->toHaveCount(1)
        ->and($toolMessages->first()->tool_name)->toBe('handoff_to_grammar_specialist')
        ->and($toolMessages->first()->tool_result['result'])->toBe('Use the present perfect: "I have already finished my homework."');

    $assistantMessages = $conversation->messages()->where('role', 'assistant')->get();
    expect($assistantMessages)->toHaveCount(1)
        ->and($assistantMessages->first()->content)->toContain('present perfect');
});
