<?php

use App\Modules\Ai\Domain\Models\AgentConversation;
use App\Modules\Interview\Domain\Models\InterviewQuestion;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

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

test('learner reviews an AI question proposal before it enters the question bank', function () {
    interviewLearner();
    $topic = test()->postJson('/api/interview/topics', ['name' => 'Reliability'])->assertCreated()->json('data');
    $draft = test()->postJson('/api/interview/drafts', [
        'kind' => 'question',
        'payload' => ['prompt_en' => 'How do you handle a timeout?', 'prompt_ru' => 'Как вы обрабатываете таймаут?', 'topic_id' => $topic['id'], 'tags' => ['reliability']],
    ])->assertCreated()->assertJsonPath('data.status', 'pending')->json('data');

    test()->getJson('/api/interview/drafts')->assertOk()->assertJsonPath('data.0.id', $draft['id']);
    test()->postJson('/api/interview/drafts/'.$draft['id'].'/confirm')->assertOk()
        ->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.result.prompt_en', 'How do you handle a timeout?')
        ->assertJsonPath('data.result.topic.name', 'Reliability')->assertJsonPath('data.result.tags.0', 'reliability');
    test()->getJson('/api/interview/questions')->assertOk()->assertJsonPath('data.0.prompt_en', 'How do you handle a timeout?')
        ->assertJsonPath('data.0.answers.short.en', null)->assertJsonPath('data.0.answers.full.en', null);
    test()->postJson('/api/interview/drafts/'.$draft['id'].'/confirm')->assertStatus(409);
    test()->postJson('/api/interview/drafts/'.$draft['id'].'/reject')->assertStatus(409);

    $rejected = test()->postJson('/api/interview/drafts', [
        'kind' => 'question', 'payload' => ['prompt_en' => 'What is a retry?', 'prompt_ru' => 'Что такое повтор?'],
    ])->assertCreated()->json('data');
    test()->postJson('/api/interview/drafts/'.$rejected['id'].'/reject')->assertOk()->assertJsonPath('data.status', 'rejected');
    test()->postJson('/api/interview/drafts/'.$rejected['id'].'/confirm')->assertStatus(409);

    $other = User::factory()->create();
    test()->actingAs($other)->getJson('/api/interview/drafts')->assertOk()->assertJsonCount(0, 'data');
    test()->postJson('/api/interview/drafts/'.$draft['id'].'/confirm')->assertNotFound();
});

test('learner can create and reopen a private coached or mock practice session', function () {
    $learner = interviewLearner();
    test()->putJson('/api/interview/profile', ['career_goal' => 'Junior developer'])->assertOk();
    $question = test()->postJson('/api/interview/questions', [
        'prompt_en' => 'Describe an API you built.',
        'prompt_ru' => 'Опишите API, который вы создали.',
    ])->assertCreated()->json('data');

    $session = test()->postJson('/api/interview/sessions', [
        'mode' => 'coached', 'question_ids' => [$question['id']], 'question_count' => 1,
    ])->assertCreated()->json('data');

    expect($session['mode'])->toBe('coached')
        ->and($session['status'])->toBe('active')
        ->and($session['conversation_id'])->toBeInt();
    test()->getJson('/api/interview/sessions/'.$session['id'])
        ->assertOk()->assertJsonPath('data.id', $session['id'])
        ->assertJsonPath('data.questions.0.prompt_en', 'Describe an API you built.')
        ->assertJsonPath('data.profile.career_goal', 'Junior developer');
    test()->postJson('/api/interview/sessions', ['mode' => 'mock', 'question_count' => 3])->assertCreated();
    expect(AgentConversation::query()->where('created_by', $learner->id)->where('agent_type', 'interview')->count())->toBe(2);
    test()->getJson('/api/interview/sessions')->assertOk()->assertJsonCount(2, 'data');

    Queue::fake();
    config(['ai.agent.enabled' => true, 'ai.agent.turns_per_day' => 1]);
    test()->postJson('/api/interview/sessions/'.$session['id'].'/messages', ['content' => 'I built a small API.'])
        ->assertAccepted()->assertJsonPath('data.status', 'queued');
    Queue::assertPushed(\App\Modules\Ai\Interfaces\Jobs\RunAgentTurnJob::class, fn ($job) => $job->conversationId === $session['conversation_id']);
    test()->postJson('/api/interview/sessions/'.$session['id'].'/messages', ['content' => 'More detail.'])->assertStatus(429);
    test()->postJson('/api/interview/sessions/'.$session['id'].'/complete')->assertOk()->assertJsonPath('data.status', 'completed');
    test()->postJson('/api/interview/sessions/'.$session['id'].'/messages', ['content' => 'A late message'])->assertStatus(409);

    $other = User::factory()->create();
    test()->actingAs($other)->getJson('/api/interview/sessions/'.$session['id'])->assertNotFound();
    test()->postJson('/api/interview/sessions/'.$session['id'].'/complete')->assertNotFound();
});

test('mock practice selects the requested count from the chosen topic subtree', function () {
    interviewLearner();
    $parent = test()->postJson('/api/interview/topics', ['name' => 'Technical'])->assertCreated()->json('data');
    $child = test()->postJson('/api/interview/topics', ['name' => 'HTTP', 'parent_id' => $parent['id']])->assertCreated()->json('data');
    $first = test()->postJson('/api/interview/questions', ['prompt_en' => 'What is REST?', 'topic_id' => $child['id']])->assertCreated()->json('data');
    $second = test()->postJson('/api/interview/questions', ['prompt_en' => 'What is an API?', 'topic_id' => $parent['id']])->assertCreated()->json('data');
    test()->postJson('/api/interview/questions', ['prompt_en' => 'Tell me about teamwork.'])->assertCreated();

    test()->postJson('/api/interview/sessions', [
        'mode' => 'mock', 'question_count' => 2, 'topic_id' => $parent['id'], 'focus' => 'HTTP design',
    ])->assertCreated()->assertJsonPath('data.question_count', 2)
        ->assertJsonPath('data.focus', 'HTTP design')
        ->assertJsonPath('data.questions.0.id', $first['id'])
        ->assertJsonPath('data.questions.1.id', $second['id'])
        ->assertJsonCount(2, 'data.questions');
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
