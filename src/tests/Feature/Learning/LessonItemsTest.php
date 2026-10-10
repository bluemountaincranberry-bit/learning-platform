<?php

use App\Modules\Learning\Domain\Models\Lesson;
use App\Modules\Learning\Domain\Models\LessonAnalysisRun;
use App\Modules\Learning\Domain\Models\LessonGrammarCandidate;
use App\Modules\Learning\Domain\Models\LessonLexemeCandidate;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\Srs\Domain\Models\SrsReview;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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

test('lesson owner can permanently delete selected candidates atomically without deleting learner history', function () {
    $user = lessonItemsStudent();
    $lesson = lessonItemsLesson($user->id);
    $candidates = collect(['first', 'second'])->map(fn (string $text) => LessonLexemeCandidate::query()->create([
        'lesson_id' => $lesson->id, 'text' => $text, 'normalized_text' => $text, 'type' => 'word', 'status' => 'new', 'source' => 'manual',
    ]));
    $lexeme = Lexeme::query()->create([
        'slug' => 'first-word', 'language' => 'en', 'lemma' => 'first', 'normalized_lemma' => 'first', 'status' => 'published',
    ]);
    $candidates[0]->update(['matched_lexeme_id' => $lexeme->id]);
    DB::table('user_lexeme_sources')->insert([
        ['user_id' => $user->id, 'lexeme_id' => $lexeme->id, 'source_kind' => 'lesson', 'lesson_lexeme_candidate_id' => $candidates[0]->id, 'source_text' => 'first', 'display_label_snapshot' => 'Lesson', 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => $user->id, 'lexeme_id' => $lexeme->id, 'source_kind' => 'manual', 'lesson_lexeme_candidate_id' => null, 'source_text' => 'first', 'display_label_snapshot' => 'My words', 'created_at' => now(), 'updated_at' => now()],
    ]);
    $card = SrsCard::query()->create([
        'user_id' => $user->id, 'lexeme_id' => $lexeme->id, 'content_id' => null, 'item_key' => null,
        'state' => 'learning', 'interval_days' => 4, 'ease_factor' => 2.5, 'next_review_at' => now()->addDays(4),
    ]);
    $review = SrsReview::query()->create(['srs_card_id' => $card->id, 'grade' => 2, 'prev_interval' => 1, 'new_interval' => 4, 'reviewed_at' => now()]);

    test()->deleteJson("/api/lessons/{$lesson->id}/lexemes/permanently", ['ids' => $candidates->pluck('id')->all()])
        ->assertOk()->assertJsonCount(2, 'deleted_ids');
    expect(LessonLexemeCandidate::withTrashed()->whereIn('id', $candidates->pluck('id'))->count())->toBe(0);
    expect(Lexeme::query()->whereKey($lexeme->id)->exists())->toBeTrue()
        ->and(SrsCard::query()->whereKey($card->id)->exists())->toBeTrue()
        ->and(SrsReview::query()->whereKey($review->id)->exists())->toBeTrue()
        ->and(DB::table('user_lexeme_sources')->where('lexeme_id', $lexeme->id)->whereNull('lesson_lexeme_candidate_id')->exists())->toBeTrue()
        ->and(DB::table('user_lexeme_sources')->where('lesson_lexeme_candidate_id', $candidates[0]->id)->exists())->toBeFalse();
});

test('bulk lesson deletion rejects mixed lesson ids without deleting any candidate', function () {
    $user = lessonItemsStudent();
    $lesson = lessonItemsLesson($user->id);
    $otherLesson = lessonItemsLesson($user->id);
    $selected = LessonLexemeCandidate::query()->create([
        'lesson_id' => $lesson->id, 'text' => 'keep me', 'normalized_text' => 'keep me', 'type' => 'word', 'status' => 'new',
    ]);
    $foreign = LessonLexemeCandidate::query()->create([
        'lesson_id' => $otherLesson->id, 'text' => 'foreign', 'normalized_text' => 'foreign', 'type' => 'word', 'status' => 'new',
    ]);

    test()->deleteJson("/api/lessons/{$lesson->id}/lexemes/permanently", ['ids' => [$selected->id, $foreign->id]])->assertNotFound();
    expect(LessonLexemeCandidate::withTrashed()->whereKey($selected->id)->exists())->toBeTrue()
        ->and(LessonLexemeCandidate::withTrashed()->whereKey($foreign->id)->exists())->toBeTrue();
});

test('lesson words can be added idempotently to My words with a practice link and lesson source', function () {
    $user = lessonItemsStudent();
    $lesson = lessonItemsLesson($user->id);
    $lesson->update(['title' => 'Travel class', 'language' => 'en']);
    $candidate = LessonLexemeCandidate::query()->create([
        'lesson_id' => $lesson->id,
        'text' => 'look after',
        'normalized_text' => 'look after',
        'type' => 'phrasal_verb',
        'example' => 'I look after my sister.',
        'status' => 'new',
        'source' => 'manual',
    ]);

    $first = test()->postJson("/api/lessons/{$lesson->id}/lexemes/{$candidate->id}/add-to-my-words")
        ->assertOk()
        ->assertJsonPath('in_my_words', true)
        ->assertJsonPath('in_review', false)
        ->assertJsonPath('lemma', 'look after');
    $lexemeId = $first->json('lexeme_id');

    test()->postJson("/api/lessons/{$lesson->id}/lexemes/{$candidate->id}/add-to-my-words")
        ->assertOk()->assertJsonPath('lexeme_id', $lexemeId);

    expect($candidate->fresh()->matched_lexeme_id)->toBe($lexemeId)
        ->and(DB::table('user_lexeme_sources')->where('user_id', $user->id)->where('lesson_lexeme_candidate_id', $candidate->id)->count())->toBe(1)
        ->and(DB::table('srs_cards')->where('user_id', $user->id)->where('lexeme_id', $lexemeId)->whereNull('deactivated_at')->count())->toBe(0);

    test()->getJson("/api/lessons/{$lesson->id}")->assertOk()
        ->assertJsonPath('lexemes.0.in_my_words', true)
        ->assertJsonPath('lexemes.0.matched_lexeme_id', $lexemeId);
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

    $candidate = LessonLexemeCandidate::query()->create([
        'lesson_id' => $lesson->id, 'text' => 'duplicate ids', 'normalized_text' => 'duplicate ids', 'type' => 'word', 'status' => 'new',
    ]);
    test()->deleteJson("/api/lessons/{$lesson->id}/lexemes/permanently", ['ids' => [$candidate->id, $candidate->id]])
        ->assertUnprocessable()->assertJsonValidationErrors(['ids.1']);
    expect(LessonLexemeCandidate::query()->whereKey($candidate->id)->exists())->toBeTrue();
});
