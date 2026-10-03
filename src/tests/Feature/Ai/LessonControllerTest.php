<?php

use App\Modules\Ai\Interfaces\Jobs\RunAgentTurnJob;
use App\Modules\Ai\Interfaces\Jobs\RunLessonAnalysisJob;
use App\Modules\Ai\Domain\Models\AgentConversation;
use App\Modules\Ai\Domain\Models\Lesson;
use App\Modules\User\Models\User;
use App\Modules\Ai\Application\Agent\LessonAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['ai.agent.enabled' => true, 'ai.provider' => 'openai']);
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

test('store returns 503 when the agent feature is disabled', function () {
    config(['ai.agent.enabled' => false]);
    actingLessonStudent();

    test()->postJson('/api/lessons')->assertStatus(503);
});

test('non-student roles get 403 from the lesson endpoints', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('admin');
    test()->actingAs($user);

    test()->postJson('/api/lessons')->assertStatus(403);
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
