<?php

use App\Modules\Ai\Application\Agent\LessonPdfNotesFolder;
use App\Modules\Ai\Domain\Models\AgentConversation;
use App\Modules\Learning\Domain\Models\Lesson;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function lessonWithPdfMessage(string $fixture): array
{
    Storage::fake('local');
    $user = User::factory()->create();
    $lesson = Lesson::query()->create(['user_id' => $user->id, 'status' => Lesson::STATUS_ACTIVE]);
    $conversation = AgentConversation::query()->create([
        'created_by' => $user->id, 'lesson_id' => $lesson->id, 'status' => 'active', 'agent_type' => 'lesson_capture',
    ]);
    Storage::disk('local')->put('agent-uploads/list.pdf', file_get_contents(base_path('tests/Fixtures/pdf/'.$fixture)));
    $message = $conversation->messages()->create([
        'role' => 'user', 'content' => 'pdf', 'attachment_path' => 'agent-uploads/list.pdf', 'attachment_name' => 'list.pdf',
    ]);

    return [$lesson, $conversation, $message];
}

test('the lesson notes get the whole PDF, not the excerpt the model saw', function () {
    [$lesson, $conversation, $message] = lessonWithPdfMessage('wordlist-unit-1d.pdf');

    app(LessonPdfNotesFolder::class)->fold($conversation, $message->id, 'only the first 8000 characters');

    $notes = (string) $lesson->fresh()->source_text;
    expect(mb_strlen($notes))->toBeGreaterThan(config('ai.analysis.max_transcript_chars'))
        ->and($notes)->toContain('to encourage smn to do smth')
        ->and($notes)->toContain('адвокатом дьявола')
        ->and($notes)->not->toContain('only the first 8000');
});

test('falls back to the tool excerpt when the attachment cannot be re-read', function () {
    [$lesson, $conversation] = lessonWithPdfMessage('wordlist-unit-1d.pdf');

    app(LessonPdfNotesFolder::class)->fold($conversation, 99999, "excerpt text\n");

    expect($lesson->fresh()->source_text)->toBe('excerpt text');
});
