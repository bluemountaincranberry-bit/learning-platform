<?php

use App\Modules\Learning\Domain\Models\Lesson;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('lesson fields can be created edited and archived without deleting the lesson', function () {
    config(['ai.enabled' => false, 'ai.agent.enabled' => false]);
    $user = User::factory()->create();
    test()->actingAs($user);

    $lessonId = test()->postJson('/api/lessons', [
        'title' => 'French class',
        'lesson_date' => '2026-10-05',
        'teacher' => 'Marie',
        'topic' => 'Travel',
        'language' => 'ja',
        'tags' => ['speaking', 'review'],
        'notes' => 'We practiced directions.',
        'homework' => 'Read page 12.',
    ])->assertCreated()->json('lesson_id');

    test()->getJson("/api/lessons/{$lessonId}")->assertOk()
        ->assertJsonPath('title', 'French class')
        ->assertJsonPath('lesson_date', '2026-10-05T00:00:00.000000Z')
        ->assertJsonPath('teacher', 'Marie')
        ->assertJsonPath('topic', 'Travel')
        ->assertJsonPath('language', 'ja')
        ->assertJsonPath('tags', ['speaking', 'review'])
        ->assertJsonPath('notes', 'We practiced directions.')
        ->assertJsonPath('homework', 'Read page 12.');

    test()->putJson("/api/lessons/{$lessonId}", [
        'title' => 'French class — week 2',
        'lesson_date' => '2026-10-12',
        'teacher' => 'Marie',
        'topic' => 'At the station',
        'language' => 'ja',
        'tags' => ['travel'],
        'notes' => 'Updated notes.',
        'homework' => 'Workbook page 13.',
    ])->assertOk();

    test()->getJson("/api/lessons/{$lessonId}")->assertOk()
        ->assertJsonPath('title', 'French class — week 2')
        ->assertJsonPath('notes', 'Updated notes.')
        ->assertJsonPath('tags', ['travel']);

    test()->deleteJson("/api/lessons/{$lessonId}")->assertOk();
    test()->getJson('/api/lessons')->assertOk()->assertJsonCount(0, 'data');
    test()->getJson('/api/lessons?status=invalid')->assertUnprocessable();
    test()->getJson('/api/lessons?status=archived')->assertOk()->assertJsonPath('data.0.id', $lessonId);
    test()->postJson("/api/lessons/{$lessonId}/restore")->assertOk();
    test()->getJson('/api/lessons')->assertOk()->assertJsonPath('data.0.id', $lessonId);

    expect(Lesson::query()->findOrFail($lessonId)->status)->toBe(Lesson::STATUS_ACTIVE);
});

test('a lesson owner is the only user who can edit or archive it', function () {
    $owner = User::factory()->create();
    $lesson = Lesson::query()->create(['user_id' => $owner->id, 'status' => Lesson::STATUS_ACTIVE]);
    test()->actingAs(User::factory()->create());

    test()->putJson("/api/lessons/{$lesson->id}", ['notes' => 'Intrusion'])->assertNotFound();
    test()->deleteJson("/api/lessons/{$lesson->id}")->assertNotFound();

    expect($lesson->fresh()->status)->toBe(Lesson::STATUS_ACTIVE)
        ->and($lesson->fresh()->notes)->toBeNull();
});
