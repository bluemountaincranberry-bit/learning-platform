<?php

use App\Modules\Interview\Domain\Models\InterviewQuestion;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function interviewLearner(): User
{
    $user = User::factory()->create();
    test()->actingAs($user);

    return $user;
}

test('learner can organize and search private bilingual interview questions', function () {
    $learner = interviewLearner();
    $topic = test()->postJson('/api/interview/topics', ['name' => 'Technical', 'parent_id' => null])->assertCreated()->json('data');
    $child = test()->postJson('/api/interview/topics', ['name' => 'HTTP', 'parent_id' => $topic['id']])->assertCreated()->json('data');
    $leaf = test()->postJson('/api/interview/topics', ['name' => 'REST', 'parent_id' => $child['id']])->assertCreated()->json('data');

    $question = test()->postJson('/api/interview/questions', [
        'topic_id' => $leaf['id'],
        'prompt_en' => 'What is an API?',
        'prompt_ru' => 'Что такое API?',
        'tags' => ['REST', 'fundamentals'],
        'answers' => [
            'short' => ['en' => 'A contract between software.', 'ru' => 'Контракт между программами.'],
            'full' => ['en' => 'An API defines how systems communicate.', 'ru' => 'API задаёт правила общения систем.'],
        ],
    ])->assertCreated()->json('data');

    test()->getJson('/api/interview/questions?search=Что&topic_id='.$topic['id'])
        ->assertOk()->assertJsonPath('data.0.id', $question['id'])
        ->assertJsonPath('data.0.answers.short.en', 'A contract between software.')
        ->assertJsonPath('data.0.topic.parent_id', $child['id']);
    test()->putJson('/api/interview/topics/'.$topic['id'], ['name' => 'Technical', 'parent_id' => $leaf['id']])->assertUnprocessable();

    $updated = test()->putJson('/api/interview/questions/'.$question['id'], [
        'answers' => ['short' => ['en' => 'An interface between software.', 'ru' => 'Контракт между программами.']],
    ])->assertOk()->json('data');
    expect($updated['answers']['short']['revisions'])->toHaveCount(1);
    $revisionId = $updated['answers']['short']['revisions'][0]['id'];
    test()->postJson('/api/interview/questions/'.$question['id'].'/answers/'.$updated['answers']['short']['id'].'/revisions/'.$revisionId.'/restore')
        ->assertOk()->assertJsonPath('data.answers.short.en', 'A contract between software.');

    expect($learner->id)->toBeGreaterThan(0);
});

test('interview questions and topics are private and foreign resources return not found', function () {
    $owner = interviewLearner();
    $topic = test()->postJson('/api/interview/topics', ['name' => 'Behavioral'])->assertCreated()->json('data');
    $question = test()->postJson('/api/interview/questions', [
        'prompt_en' => 'Tell me about a challenge.', 'prompt_ru' => 'Расскажите о трудности.', 'topic_id' => $topic['id'],
    ])->assertCreated()->json('data');

    $other = User::factory()->create();
    test()->actingAs($other)->getJson('/api/interview/questions')->assertOk()->assertJsonCount(0, 'data');
    test()->getJson('/api/interview/questions/'.$question['id'])->assertNotFound();
    test()->deleteJson('/api/interview/topics/'.$topic['id'])->assertNotFound();
});

test('profile supports a goal and optional milestones', function () {
    interviewLearner();

    test()->putJson('/api/interview/profile', [
        'career_goal' => 'Junior Copilot Studio Developer',
        'milestones' => [['title' => 'Build a portfolio bot', 'target_date' => '2026-12-01']],
    ])->assertOk()->assertJsonPath('data.career_goal', 'Junior Copilot Studio Developer')
        ->assertJsonPath('data.milestones.0.title', 'Build a portfolio bot');
});

test('starter seeding is repeatable and preserves learner edits', function () {
    $learner = interviewLearner();
    Artisan::call('interview:seed-starter', ['--user' => $learner->id]);
    expect(InterviewQuestion::query()->where('user_id', $learner->id)->count())->toBe(15);

    $question = InterviewQuestion::query()->where('user_id', $learner->id)->firstOrFail();
    $question->update(['prompt_ru' => 'My own translation']);
    Artisan::call('interview:seed-starter', ['--user' => $learner->id]);

    expect(InterviewQuestion::query()->where('user_id', $learner->id)->count())->toBe(15)
        ->and($question->fresh()->prompt_ru)->toBe('My own translation');
});
