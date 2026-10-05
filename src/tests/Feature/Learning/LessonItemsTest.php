<?php

use App\Modules\Learning\Domain\Models\Lesson;
use App\Modules\Learning\Domain\Models\LessonAnalysisRun;
use App\Modules\Learning\Domain\Models\LessonGrammarCandidate;
use App\Modules\Learning\Domain\Models\LessonLexemeCandidate;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function lessonItemsStudent(): User
{
    $user = User::factory()->create();
    test()->actingAs($user);

    return $user;
}

function lessonItemsLesson(int $ownerId): Lesson
{
    return Lesson::query()->create(['user_id' => $ownerId, 'status' => Lesson::STATUS_ACTIVE]);
}

test('lesson owner can add edit and recoverably delete a word without AI', function () {
    config(['ai.enabled' => false, 'ai.agent.enabled' => false]);
    $user = lessonItemsStudent();
    $lesson = lessonItemsLesson($user->id);

    $created = test()->postJson("/api/lessons/{$lesson->id}/lexemes", [
        'text' => 'look after', 'type' => 'phrasal_verb', 'translation' => 'заботиться', 'example' => 'I look after my sister.',
    ])->assertCreated()->assertJsonPath('text', 'look after')->assertJsonPath('source', 'manual');

    $id = $created->json('id');
    test()->putJson("/api/lessons/{$lesson->id}/lexemes/{$id}", ['text' => 'look after', 'translation' => 'присматривать', 'level' => 'B1'])
        ->assertOk()->assertJsonPath('translation', 'присматривать')->assertJsonPath('level', 'B1');
    test()->deleteJson("/api/lessons/{$lesson->id}/lexemes/{$id}")->assertNoContent();
    test()->getJson("/api/lessons/{$lesson->id}")->assertOk()->assertJsonCount(0, 'lexemes');
    test()->postJson("/api/lessons/{$lesson->id}/lexemes/{$id}/restore")->assertOk();
    test()->getJson("/api/lessons/{$lesson->id}")->assertOk()->assertJsonPath('lexemes.0.text', 'look after');
});

test('lesson owner can add edit and recoverably delete grammar points', function () {
    $user = lessonItemsStudent();
    $lesson = lessonItemsLesson($user->id);

    $created = test()->postJson("/api/lessons/{$lesson->id}/grammar", [
        'title' => 'Past habits', 'summary' => 'Use used to for past states.', 'example' => 'I used to live here.',
    ])->assertCreated()->assertJsonPath('title', 'Past habits');

    $id = $created->json('id');
    test()->putJson("/api/lessons/{$lesson->id}/grammar/{$id}", ['title' => 'Used to', 'summary' => 'Past states'])
        ->assertOk()->assertJsonPath('title', 'Used to');
    test()->deleteJson("/api/lessons/{$lesson->id}/grammar/{$id}")->assertNoContent();
    test()->getJson("/api/lessons/{$lesson->id}")->assertOk()->assertJsonCount(0, 'grammar');
    test()->postJson("/api/lessons/{$lesson->id}/grammar/{$id}/restore")->assertOk();
});

test('lesson owner can add edit and recoverably delete corrections', function () {
    $user = lessonItemsStudent();
    $lesson = lessonItemsLesson($user->id);

    $created = test()->postJson("/api/lessons/{$lesson->id}/corrections", [
        'original_text' => 'She go to work yesterday.', 'corrected_text' => 'She went to work yesterday.', 'explanation' => 'Use past simple.',
    ])->assertCreated()->assertJsonPath('corrected_text', 'She went to work yesterday.');

    $id = $created->json('id');
    test()->putJson("/api/lessons/{$lesson->id}/corrections/{$id}", ['original_text' => 'She goes yesterday.', 'corrected_text' => 'She went yesterday.'])
        ->assertOk()->assertJsonPath('original_text', 'She goes yesterday.');
    test()->deleteJson("/api/lessons/{$lesson->id}/corrections/{$id}")->assertNoContent();
    test()->getJson("/api/lessons/{$lesson->id}")->assertOk()->assertJsonCount(0, 'corrections');
    test()->postJson("/api/lessons/{$lesson->id}/corrections/{$id}/restore")->assertOk();
});

test('AI-extracted words and grammar can be edited and deleted through lesson item routes', function () {
    $user = lessonItemsStudent();
    $lesson = lessonItemsLesson($user->id);
    $run = $lesson->analysisRuns()->create(['status' => LessonAnalysisRun::STATUS_COMPLETED]);
    $word = LessonLexemeCandidate::query()->create([
        'lesson_analysis_run_id' => $run->id, 'lesson_id' => $lesson->id, 'text' => 'take care of', 'normalized_text' => 'take care of',
        'type' => 'phrasal_verb', 'status' => 'new', 'source' => 'ai',
    ]);
    $grammar = LessonGrammarCandidate::query()->create([
        'lesson_analysis_run_id' => $run->id, 'lesson_id' => $lesson->id, 'title' => 'Present perfect', 'status' => 'new', 'source' => 'ai',
    ]);

    test()->putJson("/api/lessons/{$lesson->id}/lexemes/{$word->id}", ['text' => 'take care of', 'translation' => 'заботиться'])
        ->assertOk()->assertJsonPath('translation', 'заботиться')->assertJsonPath('source', 'ai');
    test()->putJson("/api/lessons/{$lesson->id}/grammar/{$grammar->id}", ['title' => 'Perfect aspect'])
        ->assertOk()->assertJsonPath('title', 'Perfect aspect')->assertJsonPath('source', 'ai');
    test()->deleteJson("/api/lessons/{$lesson->id}/lexemes/{$word->id}")->assertNoContent();
    test()->deleteJson("/api/lessons/{$lesson->id}/grammar/{$grammar->id}")->assertNoContent();
});

test('lesson items are private and cannot be changed through another lesson', function () {
    $owner = User::factory()->create();
    $lesson = lessonItemsLesson($owner->id);
    $run = $lesson->analysisRuns()->create(['status' => LessonAnalysisRun::STATUS_COMPLETED]);
    $word = LessonLexemeCandidate::query()->create([
        'lesson_analysis_run_id' => $run->id, 'lesson_id' => $lesson->id, 'text' => 'although', 'normalized_text' => 'although', 'status' => 'new',
    ]);
    $grammar = LessonGrammarCandidate::query()->create([
        'lesson_analysis_run_id' => $run->id, 'lesson_id' => $lesson->id, 'title' => 'Past perfect', 'status' => 'new',
    ]);
    $correction = $lesson->corrections()->create([
        'original_text' => 'I has eaten.', 'corrected_text' => 'I have eaten.',
    ]);

    $other = lessonItemsStudent();
    $otherLesson = lessonItemsLesson($other->id);

    test()->getJson("/api/lessons/{$lesson->id}")->assertNotFound();
    test()->putJson("/api/lessons/{$otherLesson->id}/lexemes/{$word->id}", ['text' => 'although'])
        ->assertNotFound();
    test()->deleteJson("/api/lessons/{$otherLesson->id}/lexemes/{$word->id}")->assertNotFound();
    test()->putJson("/api/lessons/{$otherLesson->id}/grammar/{$grammar->id}", ['title' => 'Past perfect'])
        ->assertNotFound();
    test()->deleteJson("/api/lessons/{$otherLesson->id}/grammar/{$grammar->id}")->assertNotFound();
    test()->putJson("/api/lessons/{$otherLesson->id}/corrections/{$correction->id}", ['corrected_text' => 'I have eaten.'])
        ->assertNotFound();
    test()->deleteJson("/api/lessons/{$otherLesson->id}/corrections/{$correction->id}")->assertNotFound();
});

test('lesson item fields are validated', function () {
    $user = lessonItemsStudent();
    $lesson = lessonItemsLesson($user->id);

    test()->postJson("/api/lessons/{$lesson->id}/lexemes", ['text' => 'word', 'type' => 'not-a-type'])
        ->assertUnprocessable()->assertJsonValidationErrors(['type']);
    test()->postJson("/api/lessons/{$lesson->id}/grammar", ['title' => ''])
        ->assertUnprocessable()->assertJsonValidationErrors(['title']);
    test()->postJson("/api/lessons/{$lesson->id}/corrections", ['original_text' => 'only one side'])
        ->assertUnprocessable()->assertJsonValidationErrors(['corrected_text']);
});
