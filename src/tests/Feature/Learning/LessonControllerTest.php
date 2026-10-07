<?php

use App\Modules\Ai\Application\Agent\LessonAgentService;
use App\Modules\Ai\Domain\Models\AgentConversation;
use App\Modules\Ai\Interfaces\Jobs\RunAgentTurnJob;
use App\Modules\Ai\Interfaces\Jobs\RunLessonAnalysisJob;
use App\Modules\Learning\Domain\Models\Lesson;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['ai.enabled' => true, 'ai.agent.enabled' => true, 'ai.provider' => 'openai']);
});

function actingLessonStudent(): User
{
    $user = User::factory()->create();
    test()->actingAs($user);

    return $user;
}

test('store creates a lesson and its lesson_capture conversation together for the acting user', function () {
    $user = actingLessonStudent();

    $response = test()->postJson('/api/lessons')->assertCreated();

    $lesson = Lesson::query()->findOrFail($response->json('lesson_id'));
    $conversation = AgentConversation::query()->findOrFail($response->json('conversation_id'));

    expect($lesson->user_id)->toBe($user->id)
        ->and($lesson->status)->toBe(Lesson::STATUS_ACTIVE)
        ->and($conversation->lesson_id)->toBe($lesson->id)
        ->and($conversation->agent_type)->toBe(LessonAgentService::AGENT_TYPE);
});

test('a lesson can be created listed and opened with AI disabled and its daily quota exhausted', function () {
    config(['ai.enabled' => false, 'ai.agent.enabled' => false, 'ai.agent.turns_per_day' => 1]);
    $user = actingLessonStudent();
    \Illuminate\Support\Facades\Cache::put('ai:agent:rate_limit:lesson:'.$user->id.':'.now()->format('Y-m-d'), 1);

    $response = test()->postJson('/api/lessons')->assertCreated();
    $lessonId = $response->json('lesson_id');
    test()->getJson('/api/lessons')->assertOk()->assertJsonPath('data.0.id', $lessonId);
    test()->getJson("/api/lessons/{$lessonId}")->assertOk()->assertJsonPath('id', $lessonId);
});

test('admin can create list and open their lessons', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('admin');
    test()->actingAs($user);

    $response = test()->postJson('/api/lessons')->assertCreated();
    $id = $response->json('lesson_id');
    test()->getJson('/api/lessons')->assertOk()->assertJsonPath('data.0.id', $id);
    test()->getJson("/api/lessons/{$id}")->assertOk()->assertJsonPath('id', $id);
});

test('a lesson belonging to another user is not visible', function () {
    $owner = User::factory()->create();
    $lesson = Lesson::query()->create(['user_id' => $owner->id, 'status' => Lesson::STATUS_ACTIVE]);
    AgentConversation::query()->create([
        'created_by' => $owner->id, 'lesson_id' => $lesson->id, 'status' => 'active', 'agent_type' => LessonAgentService::AGENT_TYPE,
    ]);

    actingLessonStudent();

    test()->getJson("/api/lessons/{$lesson->id}")->assertStatus(404);
});

test('storeMessage requires content or an attachment', function () {
    Queue::fake();
    $user = actingLessonStudent();
    $lesson = Lesson::query()->create(['user_id' => $user->id, 'status' => Lesson::STATUS_ACTIVE]);
    AgentConversation::query()->create([
        'created_by' => $user->id, 'lesson_id' => $lesson->id, 'status' => 'active', 'agent_type' => LessonAgentService::AGENT_TYPE,
    ]);

    test()->postJson("/api/lessons/{$lesson->id}/messages", [])->assertStatus(422);
    Queue::assertNothingPushed();
});

test('storeMessage appends the typed text to the lesson\'s notes and dispatches a turn', function () {
    Queue::fake();
    $user = actingLessonStudent();
    $lesson = Lesson::query()->create(['user_id' => $user->id, 'status' => Lesson::STATUS_ACTIVE, 'source_text' => 'Earlier note.']);
    $conversation = AgentConversation::query()->create([
        'created_by' => $user->id, 'lesson_id' => $lesson->id, 'status' => 'active', 'agent_type' => LessonAgentService::AGENT_TYPE,
    ]);

    test()->postJson("/api/lessons/{$lesson->id}/messages", ['content' => 'We covered phrasal verbs.'])
        ->assertStatus(202);

    $lesson->refresh();
    expect($lesson->source_text)->toContain('Earlier note.')
        ->and($lesson->source_text)->toContain('We covered phrasal verbs.');

    Queue::assertPushed(RunAgentTurnJob::class, fn ($job) => $job->conversationId === $conversation->id);
});

test('storeMessage stores an attached PDF on the local disk and records it on the message', function () {
    Storage::fake('local');
    Queue::fake();
    $user = actingLessonStudent();
    $lesson = Lesson::query()->create(['user_id' => $user->id, 'status' => Lesson::STATUS_ACTIVE]);
    $conversation = AgentConversation::query()->create([
        'created_by' => $user->id, 'lesson_id' => $lesson->id, 'status' => 'active', 'agent_type' => LessonAgentService::AGENT_TYPE,
    ]);

    $file = UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf');

    test()->postJson("/api/lessons/{$lesson->id}/messages", ['attachment' => $file])->assertStatus(202);

    $message = $conversation->messages()->latest('id')->first();
    expect($message->attachment_name)->toBe('notes.pdf')
        ->and($message->attachment_path)->not->toBeNull();
    Storage::disk('local')->assertExists($message->attachment_path);

    Queue::assertPushed(RunAgentTurnJob::class);

    $extractor = Mockery::mock(\App\Modules\Content\Application\Contracts\PdfTextExtractorInterface::class);
    $extractor->shouldReceive('extractFromPath')->twice()->andReturn('We practiced get up in the PDF.');
    app()->instance(\App\Modules\Content\Application\Contracts\PdfTextExtractorInterface::class, $extractor);
    $client = Mockery::mock(\App\Contracts\Ai\AiToolCallingClient::class);
    $client->shouldReceive('chat')->twice()->andReturn(
        new \App\Modules\Ai\Application\Agent\Data\AgentChatResponse(null, [
            new \App\Modules\Ai\Application\Agent\Data\AgentToolCall('read-pdf', 'extract_pdf_text', ['attachment_message_id' => $message->id]),
        ]),
        new \App\Modules\Ai\Application\Agent\Data\AgentChatResponse('Your notes were read.'),
    );
    app()->instance(\App\Contracts\Ai\AiToolCallingClient::class, $client);
    (new RunAgentTurnJob($conversation->id))->handle();

    expect($lesson->fresh()->source_text)->toBe('We practiced get up in the PDF.');
    test()->getJson("/api/lessons/{$lesson->id}/messages")->assertOk()
        ->assertJsonCount(2, 'messages')->assertJsonPath('is_waiting', false)
        ->assertJsonPath('messages.1.content', 'Your notes were read.');
});

test('uploading the real word-list fixture folds its extracted text into lesson notes', function () {
    Storage::fake('local');
    Queue::fake();
    $user = actingLessonStudent();
    $lesson = Lesson::query()->create(['user_id' => $user->id, 'status' => Lesson::STATUS_ACTIVE]);
    $conversation = AgentConversation::query()->create([
        'created_by' => $user->id, 'lesson_id' => $lesson->id, 'status' => 'active', 'agent_type' => LessonAgentService::AGENT_TYPE,
    ]);
    $fixture = base_path('tests/Fixtures/pdf/wordlist-unit-1d.pdf');
    $file = new UploadedFile($fixture, 'Wordlist_Unit_1D.pdf', 'application/pdf', null, true);

    test()->postJson("/api/lessons/{$lesson->id}/messages", ['attachment' => $file])->assertStatus(202);

    $message = $conversation->messages()->latest('id')->firstOrFail();
    $client = Mockery::mock(\App\Contracts\Ai\AiToolCallingClient::class);
    $client->shouldReceive('chat')->twice()->andReturn(
        new \App\Modules\Ai\Application\Agent\Data\AgentChatResponse(null, [
            new \App\Modules\Ai\Application\Agent\Data\AgentToolCall('read-pdf', 'extract_pdf_text', ['attachment_message_id' => $message->id]),
        ]),
        new \App\Modules\Ai\Application\Agent\Data\AgentChatResponse('I read the word list.'),
    );
    app()->instance(\App\Contracts\Ai\AiToolCallingClient::class, $client);

    (new RunAgentTurnJob($conversation->id))->handle();

    expect($lesson->fresh()->source_text)
        ->toContain('to encourage smn to do smth')
        ->toContain('to discourage smn from doing smth');

    $analysisPrompts = [];
    $analysisClient = Mockery::mock(\App\Contracts\Ai\AiJsonClient::class);
    $analysisClient->shouldReceive('completeJson')->atLeast()->once()->andReturnUsing(function (string $system, string $prompt) use (&$analysisPrompts): array {
        $analysisPrompts[] = $prompt;

        return ['lexemes' => [['text' => 'to encourage smn to do smth', 'type' => 'phrase', 'translation' => 'побуждать кого-либо что-либо сделать']], 'grammar' => []];
    });
    app()->instance(\App\Contracts\Ai\AiJsonClient::class, $analysisClient);

    $analysis = test()->postJson("/api/lessons/{$lesson->id}/analyze")->assertAccepted();
    app()->call([new RunLessonAnalysisJob($analysis->json('run_id')), 'handle']);
    test()->getJson("/api/lessons/{$lesson->id}")->assertOk()
        ->assertJsonPath('analysis_status', 'completed')
        ->assertJsonPath('lexemes.0.text', 'to encourage smn to do smth');
    expect(collect($analysisPrompts)->contains(fn (string $prompt) => str_contains($prompt, 'to encourage smn to do smth')))->toBeTrue();
});

test('storeMessage rejects a non-PDF attachment', function () {
    Queue::fake();
    $user = actingLessonStudent();
    $lesson = Lesson::query()->create(['user_id' => $user->id, 'status' => Lesson::STATUS_ACTIVE]);
    AgentConversation::query()->create([
        'created_by' => $user->id, 'lesson_id' => $lesson->id, 'status' => 'active', 'agent_type' => LessonAgentService::AGENT_TYPE,
    ]);

    $file = UploadedFile::fake()->create('notes.txt', 10, 'text/plain');

    test()->postJson("/api/lessons/{$lesson->id}/messages", ['attachment' => $file])->assertStatus(422);
    Queue::assertNothingPushed();
});

test('analyze rejects a lesson with no notes yet', function () {
    Queue::fake();
    $user = actingLessonStudent();
    $lesson = Lesson::query()->create(['user_id' => $user->id, 'status' => Lesson::STATUS_ACTIVE]);

    test()->postJson("/api/lessons/{$lesson->id}/analyze")->assertStatus(422);
    Queue::assertNothingPushed();
});

test('analyze creates a pending run and dispatches the analysis job', function () {
    Queue::fake();
    $user = actingLessonStudent();
    $lesson = Lesson::query()->create(['user_id' => $user->id, 'status' => Lesson::STATUS_ACTIVE, 'source_text' => 'We covered get up today.']);

    $response = test()->postJson("/api/lessons/{$lesson->id}/analyze")->assertStatus(202);

    $run = $lesson->analysisRuns()->findOrFail($response->json('run_id'));
    expect($run->status)->toBe('pending');

    Queue::assertPushed(RunLessonAnalysisJob::class, fn ($job) => $job->runId === $run->id);
});

test('analysis is unavailable when AI is disabled while the lesson stays readable', function () {
    Queue::fake();
    config(['ai.enabled' => false]);
    $user = actingLessonStudent();
    $lesson = Lesson::query()->create(['user_id' => $user->id, 'source_text' => 'We covered get up today.']);

    test()->postJson("/api/lessons/{$lesson->id}/analyze")->assertStatus(503);
    test()->getJson("/api/lessons/{$lesson->id}")->assertOk()->assertJsonPath('analysis_status', null);
    Queue::assertNothingPushed();
});

test('queued analysis persists Learning results that remain readable with AI disabled and ignores a replay', function () {
    Queue::fake();
    $user = actingLessonStudent();
    $lesson = Lesson::query()->create(['user_id' => $user->id, 'source_text' => 'We practiced get up.']);
    $client = Mockery::mock(\App\Contracts\Ai\AiJsonClient::class);
    $client->shouldReceive('completeJson')->twice()->andReturn([
        'lexemes' => [['text' => 'get up', 'type' => 'phrasal_verb', 'translation' => 'вставать']],
        'grammar' => [],
    ]);
    app()->instance(\App\Contracts\Ai\AiJsonClient::class, $client);

    $response = test()->postJson("/api/lessons/{$lesson->id}/analyze")->assertAccepted();
    $job = new RunLessonAnalysisJob($response->json('run_id'));
    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);
    config(['ai.enabled' => false, 'ai.agent.enabled' => false]);

    test()->getJson("/api/lessons/{$lesson->id}")->assertOk()
        ->assertJsonPath('analysis_status', 'completed')
        ->assertJsonCount(1, 'lexemes')
        ->assertJsonPath('lexemes.0.text', 'get up')
        ->assertJsonPath('lexemes.0.translation', 'вставать')
        ->assertJsonPath('lexemes.0.status', 'new');
    test()->getJson('/api/lessons')->assertOk()->assertJsonPath('data.0.lexeme_count', 1);
});

test('failed AI analysis leaves the lesson accessible with its notes and a failed analysis state', function () {
    Queue::fake();
    $user = actingLessonStudent();
    $lesson = Lesson::query()->create(['user_id' => $user->id, 'source_text' => 'My existing notes.']);
    $client = Mockery::mock(\App\Contracts\Ai\AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andThrow(new \App\Exceptions\AiClientException('Provider unavailable'));
    app()->instance(\App\Contracts\Ai\AiJsonClient::class, $client);

    $response = test()->postJson("/api/lessons/{$lesson->id}/analyze")->assertAccepted();
    $job = new RunLessonAnalysisJob($response->json('run_id'));
    expect(fn () => app()->call([$job, 'handle']))->toThrow(\App\Exceptions\AiClientException::class);

    test()->getJson("/api/lessons/{$lesson->id}")->assertOk()->assertJsonPath('analysis_status', 'failed');
    expect($lesson->fresh()->source_text)->toBe('My existing notes.');
});

test('guest cannot access lesson endpoints', function (string $method, string $uri) {
    test()->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/api/lessons'], ['POST', '/api/lessons'], ['GET', '/api/lessons/1'],
    ['GET', '/api/lessons/1/messages'], ['POST', '/api/lessons/1/messages'], ['POST', '/api/lessons/1/analyze'],
]);

test('another users lesson is absent from the list and cannot be read messaged or analyzed', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $lesson = Lesson::query()->create(['user_id' => $owner->id, 'source_text' => 'Private notes.']);
    actingLessonStudent();

    test()->getJson('/api/lessons')->assertOk()->assertJsonCount(0, 'data');
    test()->getJson("/api/lessons/{$lesson->id}")->assertNotFound();
    test()->getJson("/api/lessons/{$lesson->id}/messages")->assertNotFound();
    test()->postJson("/api/lessons/{$lesson->id}/messages", ['content' => 'Changed notes'])->assertNotFound();
    test()->postJson("/api/lessons/{$lesson->id}/analyze")->assertNotFound();
    Queue::assertNothingPushed();
});

test('lesson word payload includes its source language for pronunciation', function () {
    $user = actingLessonStudent();
    $lesson = Lesson::query()->create(['user_id' => $user->id, 'status' => Lesson::STATUS_ACTIVE, 'language' => 'fr']);
    $run = $lesson->analysisRuns()->create(['status' => 'completed']);
    $run->lexemeCandidates()->create([
        'lesson_id' => $lesson->id, 'text' => 'bonjour', 'normalized_text' => 'bonjour', 'type' => 'word', 'status' => 'new',
    ]);

    test()->getJson("/api/lessons/{$lesson->id}")->assertOk()
        ->assertJsonPath('lexemes.0.language', 'fr');
});
