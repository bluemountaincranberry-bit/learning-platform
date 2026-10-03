<?php

use App\Modules\Ai\Domain\Models\AgentConversation;
use App\Modules\Ai\Domain\Models\AgentMessage;
use App\Modules\User\Models\User;
use App\Modules\Ai\Application\Agent\ContentAgentService;
use App\Modules\Ai\Application\Agent\Data\AgentChatResponse;
use App\Modules\Ai\Application\Agent\StudentTutorAgentService;
use App\Contracts\Ai\AiStreamingChatClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['ai.agent.enabled' => true, 'ai.provider' => 'openai']);
});

/**
 * Parses the SSE body storeMessage() streams back (task 3.6) into its
 * decoded `data:` payloads, in order.
 *
 * @return array<int, array<string, mixed>>
 */
function parseSseEvents(string $streamedContent): array
{
    $events = [];
    foreach (explode("\n\n", trim($streamedContent)) as $rawEvent) {
        foreach (explode("\n", $rawEvent) as $line) {
            if (str_starts_with($line, 'data:')) {
                $events[] = json_decode(trim(substr($line, 5)), true);
            }
        }
    }

    return $events;
}

function fakeStreamingClient(string $finalText): \Mockery\MockInterface
{
    $client = Mockery::mock(AiStreamingChatClient::class);
    $client->shouldReceive('chatStream')->once()->andReturnUsing(
        function ($messages, $tools, $onDelta) use ($finalText) {
            $onDelta($finalText);

            return new AgentChatResponse($finalText);
        }
    );
    app()->instance(AiStreamingChatClient::class, $client);

    return $client;
}

function actingStudent(): User
{
    $user = User::factory()->create();
    test()->actingAs($user);

    return $user;
}

function actingStaff(string $role = 'admin'): User
{
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);
    test()->actingAs($user);

    return $user;
}

test('store creates a student_tutor conversation for the acting user', function () {
    $user = actingStudent();

    $response = test()->postJson('/api/tutor/conversations')->assertCreated();

    $conversation = AgentConversation::query()->findOrFail($response->json('conversation_id'));
    expect($conversation->created_by)->toBe($user->id)
        ->and($conversation->agent_type)->toBe(StudentTutorAgentService::AGENT_TYPE);
});

test('store returns 503 when the agent feature is disabled', function () {
    config(['ai.agent.enabled' => false]);
    actingStudent();

    test()->postJson('/api/tutor/conversations')->assertStatus(503);
});

test('non-student roles get 403 from the tutor conversation-creation endpoint', function () {
    actingStaff('admin');

    test()->postJson('/api/tutor/conversations')->assertStatus(403);
});

test('editor also gets 403 from the tutor conversation-creation endpoint', function () {
    actingStaff('editor');
    test()->postJson('/api/tutor/conversations')->assertStatus(403);
});

test('moderator also gets 403 from the tutor conversation-creation endpoint', function () {
    actingStaff('moderator');
    test()->postJson('/api/tutor/conversations')->assertStatus(403);
});

/**
 * Task 3.8: the access-tutor-agent gate is applied as route middleware
 * covering both endpoints (Modules\Ai/Routes/api.php), not just store() —
 * a staff account is blocked from posting a message to a conversation
 * regardless of who owns it, before the controller ever runs.
 */
test('non-student roles get 403 from the message endpoint too, even for their own conversation', function () {
    $admin = actingStaff('admin');
    $conversation = AgentConversation::query()->create([
        'created_by' => $admin->id,
        'status' => 'active',
        'agent_type' => StudentTutorAgentService::AGENT_TYPE,
    ]);

    test()->postJson("/api/tutor/conversations/{$conversation->id}/messages", ['content' => 'Hi!'])
        ->assertStatus(403);
});

test('a plain student account (no admin-panel role) is allowed through the tutor endpoints', function () {
    actingStudent();

    test()->postJson('/api/tutor/conversations')->assertCreated();
});

test('storeMessage streams the assistant reply as SSE', function () {
    actingStudent();
    $conversationId = test()->postJson('/api/tutor/conversations')->assertCreated()->json('conversation_id');

    fakeStreamingClient('Hello! How can I help you study today?');

    $response = test()->post("/api/tutor/conversations/{$conversationId}/messages", ['content' => 'Hi!'])
        ->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('text/event-stream');

    $events = parseSseEvents($response->streamedContent());
    expect($events[0])->toBe(['delta' => 'Hello! How can I help you study today?'])
        ->and($events[1]['done'])->toBeTrue()
        ->and($events[1]['message_id'])->not->toBeNull();

    $conversation = AgentConversation::query()->findOrFail($conversationId);
    expect($conversation->messages()->where('role', AgentMessage::ROLE_USER)->count())->toBe(1)
        ->and($conversation->messages()->where('role', AgentMessage::ROLE_ASSISTANT)->count())->toBe(1)
        ->and($conversation->messages()->where('role', AgentMessage::ROLE_ASSISTANT)->first()->content)
        ->toBe('Hello! How can I help you study today?');
});

/**
 * Task 6.2: the SSE stream carries `tool_start` (and its matching
 * `tool_end`) before any `delta` for the iteration that follows the tool
 * call — proves AgentLoop's onToolCallStarted()/onToolCallCompleted()
 * boundaries genuinely reach the wire during streaming, not just at the end.
 */
test('storeMessage emits tool_start before tool_end before the final delta when a tool is called', function () {
    actingStudent();
    $conversationId = test()->postJson('/api/tutor/conversations')->assertCreated()->json('conversation_id');

    $callCount = 0;
    $client = Mockery::mock(AiStreamingChatClient::class);
    $client->shouldReceive('chatStream')->twice()->andReturnUsing(
        function ($messages, $tools, $onDelta) use (&$callCount) {
            $callCount++;

            if ($callCount === 1) {
                return new AgentChatResponse(null, [new \App\Modules\Ai\Application\Agent\Data\AgentToolCall('call_1', 'get_user_level', [])]);
            }

            $onDelta('You look like a B1 so far.');

            return new AgentChatResponse('You look like a B1 so far.');
        }
    );
    app()->instance(AiStreamingChatClient::class, $client);

    $response = test()->post("/api/tutor/conversations/{$conversationId}/messages", ['content' => 'What level am I?'])
        ->assertOk();

    $events = parseSseEvents($response->streamedContent());
    $eventKeys = array_map(fn (array $e) => array_key_first($e), $events);

    expect($eventKeys)->toBe(['tool_start', 'tool_end', 'delta', 'done'])
        ->and($events[0]['tool_start'])->toBe('get_user_level')
        ->and($events[1]['tool_end'])->toBe('get_user_level');
});

/**
 * Task 6.2: a handoff tool call emits `handoff`, not `tool_start`/`tool_end`
 * — see StudentTutorAgentService::observerFor()'s docblock for why there is
 * no separate "handoff_end".
 */
test('storeMessage emits a handoff event instead of tool_start/tool_end for a handoff tool call', function () {
    actingStudent();
    $conversationId = test()->postJson('/api/tutor/conversations')->assertCreated()->json('conversation_id');

    $client = Mockery::mock(AiStreamingChatClient::class);
    $client->shouldReceive('chatStream')->once()->andReturnUsing(
        fn () => new AgentChatResponse(null, [new \App\Modules\Ai\Application\Agent\Data\AgentToolCall(
            'call_1',
            'handoff_to_grammar_specialist',
            ['task' => 'Explain present perfect.']
        )])
    );
    $client->shouldReceive('chatStream')->once()->andReturnUsing(
        function ($messages, $tools, $onDelta) {
            $onDelta('Use the present perfect here.');

            return new AgentChatResponse('Use the present perfect here.');
        }
    );
    app()->instance(AiStreamingChatClient::class, $client);

    // HandoffTool::execute() (task 4.3) always runs the target agent's own
    // AgentLoop::run() synchronously — non-streaming — even when the outer
    // turn is streaming, so the nested GrammarAgentService call needs its
    // own AiToolCallingClient double too.
    $toolClient = Mockery::mock(\App\Contracts\Ai\AiToolCallingClient::class);
    $toolClient->shouldReceive('chat')->once()->andReturn(
        new AgentChatResponse('Use the present perfect here.')
    );
    app()->instance(\App\Contracts\Ai\AiToolCallingClient::class, $toolClient);

    $response = test()->post("/api/tutor/conversations/{$conversationId}/messages", ['content' => 'Help with grammar'])
        ->assertOk();

    $events = parseSseEvents($response->streamedContent());
    $eventKeys = array_map(fn (array $e) => array_key_first($e), $events);

    expect($eventKeys)->toBe(['handoff', 'delta', 'done'])
        ->and($events[0]['handoff'])->toBe('handoff_to_grammar_specialist');
});

/**
 * Task 6.3: GenerateQuizTool's JSON reaches the final `done` SSE event as
 * `toolResults` — via the DB-mediated mechanism documented on
 * TutorConversationController::streamTurn() (tool_result is already
 * persisted by the observer before the turn finishes; this just re-reads
 * it), not a new pipe through AgentLoop/$onDelta.
 */
test('storeMessage attaches GenerateQuizTool\'s result to the done event as toolResults', function () {
    actingStudent();
    $conversationId = test()->postJson('/api/tutor/conversations')->assertCreated()->json('conversation_id');

    $callCount = 0;
    $client = Mockery::mock(AiStreamingChatClient::class);
    $client->shouldReceive('chatStream')->twice()->andReturnUsing(
        function ($messages, $tools, $onDelta) use (&$callCount) {
            $callCount++;

            if ($callCount === 1) {
                return new AgentChatResponse(null, [new \App\Modules\Ai\Application\Agent\Data\AgentToolCall(
                    'call_1',
                    'generate_quiz',
                    ['words' => ['run'], 'count' => 1]
                )]);
            }

            $onDelta('Here is a quick quiz for you.');

            return new AgentChatResponse('Here is a quick quiz for you.');
        }
    );
    app()->instance(AiStreamingChatClient::class, $client);

    $jsonClient = Mockery::mock(\App\Contracts\Ai\AiJsonClient::class);
    $jsonClient->shouldReceive('completeJson')->once()->andReturn([
        'questions' => [
            ['type' => 'gap_fill', 'prompt' => 'I ___ every morning.', 'answer' => 'run'],
        ],
    ]);
    app()->instance(\App\Contracts\Ai\AiJsonClient::class, $jsonClient);

    $response = test()->post("/api/tutor/conversations/{$conversationId}/messages", ['content' => 'Quiz me on "run"'])
        ->assertOk();

    $events = parseSseEvents($response->streamedContent());
    $done = collect($events)->firstWhere('done', true);

    expect($done)->not->toBeNull()
        ->and($done['toolResults'])->toHaveCount(1)
        ->and($done['toolResults'][0]['quiz'][0]['prompt'])->toBe('I ___ every morning.')
        ->and($done['toolResults'][0]['is_draft'])->toBeTrue();
});

test('storeMessage omits toolResults from done when no quiz tool was called', function () {
    actingStudent();
    $conversationId = test()->postJson('/api/tutor/conversations')->assertCreated()->json('conversation_id');

    fakeStreamingClient('Just a plain reply.');

    $response = test()->post("/api/tutor/conversations/{$conversationId}/messages", ['content' => 'Hi'])
        ->assertOk();

    $events = parseSseEvents($response->streamedContent());
    $done = collect($events)->firstWhere('done', true);

    expect($done)->not->toBeNull()
        ->and($done)->not->toHaveKey('toolResults');
});

test('storeMessage 404s for a conversation owned by another user', function () {
    $owner = User::factory()->create();
    $conversation = AgentConversation::query()->create([
        'created_by' => $owner->id,
        'status' => 'active',
        'agent_type' => StudentTutorAgentService::AGENT_TYPE,
    ]);

    actingStudent();

    test()->postJson("/api/tutor/conversations/{$conversation->id}/messages", ['content' => 'Hi!'])
        ->assertNotFound();
});

test('storeMessage 404s for a conversation that belongs to a different agent_type', function () {
    $user = actingStudent();
    $conversation = AgentConversation::query()->create([
        'created_by' => $user->id,
        'status' => 'active',
        'agent_type' => ContentAgentService::AGENT_TYPE,
    ]);

    test()->postJson("/api/tutor/conversations/{$conversation->id}/messages", ['content' => 'Hi!'])
        ->assertNotFound();
});

test('storeMessage persists context_type/context_ref_id/context_label when sent (task 6.1)', function () {
    actingStudent();
    $conversationId = test()->postJson('/api/tutor/conversations')->assertCreated()->json('conversation_id');

    fakeStreamingClient('Sure, let\'s talk about that content.');

    test()->post("/api/tutor/conversations/{$conversationId}/messages", [
        'content' => "Let's discuss: \"Friends S01E01\"\n\nWhat's this about?",
        'context_type' => 'content',
        'context_ref_id' => 42,
        'context_label' => 'Friends S01E01',
    ])->assertOk()->streamedContent();

    $conversation = AgentConversation::query()->findOrFail($conversationId);
    $userMessage = $conversation->messages()->where('role', AgentMessage::ROLE_USER)->first();

    expect($userMessage->context_type)->toBe('content')
        ->and($userMessage->context_ref_id)->toBe(42)
        ->and($userMessage->context_label)->toBe('Friends S01E01');
});

test('storeMessage leaves context columns null when no context is sent', function () {
    actingStudent();
    $conversationId = test()->postJson('/api/tutor/conversations')->assertCreated()->json('conversation_id');

    fakeStreamingClient('Hi there!');

    test()->post("/api/tutor/conversations/{$conversationId}/messages", ['content' => 'Hi!'])
        ->assertOk()->streamedContent();

    $conversation = AgentConversation::query()->findOrFail($conversationId);
    $userMessage = $conversation->messages()->where('role', AgentMessage::ROLE_USER)->first();

    expect($userMessage->context_type)->toBeNull()
        ->and($userMessage->context_ref_id)->toBeNull()
        ->and($userMessage->context_label)->toBeNull();
});

test('storeMessage returns 429 once the daily turn limit is reached', function () {
    actingStudent();
    config(['ai.agent.turns_per_day' => 1]);
    $conversationId = test()->postJson('/api/tutor/conversations')->assertCreated()->json('conversation_id');

    fakeStreamingClient('First reply.');

    test()->post("/api/tutor/conversations/{$conversationId}/messages", ['content' => 'First'])
        ->assertOk()->streamedContent();
    test()->postJson("/api/tutor/conversations/{$conversationId}/messages", ['content' => 'Second'])->assertStatus(429);

    $conversation = AgentConversation::query()->findOrFail($conversationId);
    expect($conversation->messages()->where('role', AgentMessage::ROLE_USER)->count())->toBe(1);
});
