<?php

use App\Contracts\Ai\AiToolCallingClient;
use App\Modules\Ai\Application\Agent\Data\AgentChatResponse;
use App\Modules\Ai\Application\Agent\Data\AgentToolCall;
use App\Modules\Ai\Application\Agent\InterviewAgentService;
use App\Modules\Ai\Domain\Models\AgentConversation;
use App\Modules\Ai\Interfaces\Jobs\RunAgentTurnJob;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Interview\Domain\Models\InterviewPracticeSession;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Interview Agent receives only confirmed context for its private coached session through AgentLoop', function () {
    $learner = User::factory()->create();
    $lexeme = Lexeme::query()->create([
        'slug' => 'en-interview-context-'.uniqid(), 'language' => 'en', 'lemma' => 'idempotent',
        'normalized_lemma' => 'idempotent', 'status' => 'published',
    ]);
    $content = Content::factory()->create();
    $contentLexeme = $content->lexemes()->create([
        'type' => ContentLexeme::TYPE_WORD, 'text' => 'idempotent', 'sort_order' => 1, 'lexeme_id' => $lexeme->id,
    ]);
    UserLexemeProgress::query()->create(['user_id' => $learner->id, 'content_lexeme_id' => $contentLexeme->id, 'lexeme_id' => $lexeme->id, 'learned_at' => now()]);
    $profile = test()->actingAs($learner)->putJson('/api/interview/profile', [
        'career_goal' => 'Junior API developer', 'experience_stories' => ['I built a study project.'],
    ])->assertOk();
    $question = test()->postJson('/api/interview/questions', [
        'prompt_en' => 'Describe an API you built.', 'prompt_ru' => 'Опишите API, который вы создали.',
    ])->assertCreated()->json('data');
    $session = test()->postJson('/api/interview/sessions', [
        'mode' => 'coached', 'question_ids' => [$question['id']],
    ])->assertCreated()->json('data');
    $conversation = AgentConversation::query()->findOrFail($session['conversation_id']);
    $conversation->messages()->create(['role' => 'user', 'content' => 'I built a small API for my study project.']);

    $toolClient = Mockery::mock(AiToolCallingClient::class);
    $toolClient->shouldReceive('chat')->twice()->andReturn(
        new AgentChatResponse(null, [new AgentToolCall('context_1', 'get_interview_practice_context', [])]),
        new AgentChatResponse('Tell me what problem your API solved.'),
    );
    app()->instance(AiToolCallingClient::class, $toolClient);

    (new RunAgentTurnJob($conversation->id))->handle();

    $toolMessage = $conversation->messages()->where('role', 'tool')->firstOrFail();
    expect($toolMessage->tool_result['mode'])->toBe('coached')
        ->and($toolMessage->tool_result['profile']['career_goal'])->toBe('Junior API developer')
        ->and($toolMessage->tool_result['questions'][0]['prompt_en'])->toBe('Describe an API you built.')
        ->and($toolMessage->tool_result['learned_english_vocabulary'][0]['lemma'])->toBe('idempotent')
        ->and($conversation->messages()->where('role', 'assistant')->first()->content)->toBe('Tell me what problem your API solved.')
        ->and(config('ai.agent.registry')[InterviewAgentService::AGENT_TYPE])->toBe(InterviewAgentService::class)
        ->and(InterviewPracticeSession::query()->where('id', $session['id'])->exists())->toBeTrue();

    $nextSession = test()->postJson('/api/interview/sessions', ['mode' => 'coached', 'question_ids' => [$question['id']]])
        ->assertCreated()->json('data');
    $nextConversation = AgentConversation::query()->findOrFail($nextSession['conversation_id']);
    $nextConversation->messages()->create(['role' => 'user', 'content' => 'Continue my interview preparation.']);
    $nextToolClient = Mockery::mock(AiToolCallingClient::class);
    $nextToolClient->shouldReceive('chat')->twice()->andReturn(
        new AgentChatResponse(null, [new AgentToolCall('context_2', 'get_interview_practice_context', [])]),
        new AgentChatResponse('Let us continue with your API experience.'),
    );
    app()->instance(AiToolCallingClient::class, $nextToolClient);
    (new RunAgentTurnJob($nextConversation->id))->handle();
    $nextContext = $nextConversation->messages()->where('role', 'tool')->firstOrFail()->tool_result;
    expect($nextContext['profile']['career_goal'])->toBe('Junior API developer')
        ->and($nextContext['learned_english_vocabulary'][0]['lemma'])->toBe('idempotent');
});

test('Interview Agent stores an AI question proposal as pending until learner confirms it', function () {
    $learner = User::factory()->create();
    $session = test()->actingAs($learner)->postJson('/api/interview/sessions', ['mode' => 'coached'])->assertCreated()->json('data');
    $conversation = AgentConversation::query()->findOrFail($session['conversation_id']);
    $conversation->messages()->create(['role' => 'user', 'content' => 'Suggest an interview question about API timeouts.']);

    $toolClient = Mockery::mock(AiToolCallingClient::class);
    $toolClient->shouldReceive('chat')->times(3)->andReturn(
        new AgentChatResponse(null, [new AgentToolCall('context_1', 'get_interview_practice_context', [])]),
        new AgentChatResponse(null, [new AgentToolCall('draft_1', 'propose_interview_question', [
            'prompt_en' => 'How do you handle an API timeout?', 'prompt_ru' => 'Как вы обрабатываете таймаут API?',
        ])]),
        new AgentChatResponse('I drafted a bilingual question for your review.'),
    );
    app()->instance(AiToolCallingClient::class, $toolClient);

    (new RunAgentTurnJob($conversation->id))->handle();

    test()->assertDatabaseHas('interview_ai_drafts', ['user_id' => $learner->id, 'kind' => 'question', 'status' => 'pending']);
    test()->getJson('/api/interview/questions')->assertOk()->assertJsonCount(0, 'data');
    test()->getJson('/api/interview/drafts')->assertOk()->assertJsonPath('data.0.payload.prompt_en', 'How do you handle an API timeout?');
    $draftId = test()->getJson('/api/interview/drafts')->json('data.0.id');
    test()->postJson('/api/interview/drafts/'.$draftId.'/confirm')->assertOk();
    test()->getJson('/api/interview/questions')->assertOk()->assertJsonPath('data.0.prompt_en', 'How do you handle an API timeout?');
});

test('Interview Agent profile proposal stays pending and changes profile only after confirmation', function () {
    $learner = User::factory()->create();
    test()->actingAs($learner)->putJson('/api/interview/profile', [
        'milestones' => [['title' => 'Portfolio project', 'target_date' => '2026-11-01']],
    ])->assertOk();
    $session = test()->actingAs($learner)->postJson('/api/interview/sessions', ['mode' => 'coached'])->assertCreated()->json('data');
    $conversation = AgentConversation::query()->findOrFail($session['conversation_id']);
    $conversation->messages()->create(['role' => 'user', 'content' => 'I am aiming for a junior developer role and have built a study project.']);

    $toolClient = Mockery::mock(AiToolCallingClient::class);
    $toolClient->shouldReceive('chat')->times(3)->andReturn(
        new AgentChatResponse(null, [new AgentToolCall('context_1', 'get_interview_practice_context', [])]),
        new AgentChatResponse(null, [new AgentToolCall('profile_1', 'propose_interview_profile_update', [
            'career_goal' => 'Junior developer', 'experience_stories' => ['I built a study project.'],
            'milestones' => [['title' => 'Portfolio project', 'target_date' => '2026-12-01']],
        ])]),
        new AgentChatResponse('I drafted profile updates from the details you shared. Please review them.'),
    );
    app()->instance(AiToolCallingClient::class, $toolClient);

    (new RunAgentTurnJob($conversation->id))->handle();

    test()->getJson('/api/interview/profile')->assertOk()->assertJsonPath('data.career_goal', null)
        ->assertJsonPath('data.milestones.0.target_date', '2026-11-01');
    $draft = test()->getJson('/api/interview/drafts')->assertOk()->assertJsonPath('data.0.kind', 'profile')
        ->assertJsonPath('data.0.payload.career_goal', 'Junior developer')->json('data.0');
    test()->postJson('/api/interview/drafts/'.$draft['id'].'/confirm')->assertOk()
        ->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.result.career_goal', 'Junior developer');
    test()->getJson('/api/interview/profile')->assertOk()
        ->assertJsonPath('data.career_goal', 'Junior developer')
        ->assertJsonPath('data.experience_stories.0', 'I built a study project.')
        ->assertJsonCount(1, 'data.milestones')->assertJsonPath('data.milestones.0.target_date', '2026-12-01');
});

test('Interview Agent answer revision is a previewable draft until confirmed and retains the prior wording', function () {
    $learner = User::factory()->create();
    $question = test()->actingAs($learner)->postJson('/api/interview/questions', [
        'prompt_en' => 'Describe a project you built.',
        'prompt_ru' => 'Опишите проект, который вы создали.',
        'answers' => ['short' => ['en' => 'I built a study app.', 'ru' => 'Я сделал учебное приложение.']],
    ])->assertCreated()->json('data');
    $session = test()->postJson('/api/interview/sessions', ['mode' => 'coached', 'question_ids' => [$question['id']]])->assertCreated()->json('data');
    $conversation = AgentConversation::query()->findOrFail($session['conversation_id']);
    $conversation->messages()->create(['role' => 'user', 'content' => 'Please save the revised short answer: I built an app to help learners practise English.']);

    $toolClient = Mockery::mock(AiToolCallingClient::class);
    $toolClient->shouldReceive('chat')->times(3)->andReturn(
        new AgentChatResponse(null, [new AgentToolCall('context_1', 'get_interview_practice_context', [])]),
        new AgentChatResponse(null, [new AgentToolCall('answer_1', 'propose_interview_answer_revision', [
            'question_id' => $question['id'], 'variant' => 'short',
            'text_en' => 'I built an app to help learners practise English.',
            'text_ru' => 'Я создал приложение, которое помогает изучать английский.',
        ])]),
        new AgentChatResponse('I prepared the bilingual short-answer revision for review.'),
    );
    app()->instance(AiToolCallingClient::class, $toolClient);

    (new RunAgentTurnJob($conversation->id))->handle();

    test()->getJson('/api/interview/questions/'.$question['id'])->assertOk()
        ->assertJsonPath('data.answers.short.en', 'I built a study app.')
        ->assertJsonCount(0, 'data.answers.short.revisions');
    $draft = test()->getJson('/api/interview/drafts')->assertOk()->assertJsonPath('data.0.kind', 'answer')
        ->assertJsonPath('data.0.payload.question_prompt_en', 'Describe a project you built.')
        ->assertJsonPath('data.0.payload.text_en', 'I built an app to help learners practise English.')
        ->json('data.0');
    test()->postJson('/api/interview/drafts/'.$draft['id'].'/confirm')->assertOk()
        ->assertJsonPath('data.result.answers.short.en', 'I built an app to help learners practise English.')
        ->assertJsonPath('data.result.answers.short.revisions.0.text_en', 'I built a study app.');
});

test('Interview vocabulary suggestion is separate and enters My words only after confirmation', function () {
    $learner = User::factory()->create();
    $session = test()->actingAs($learner)->postJson('/api/interview/sessions', ['mode' => 'coached'])->assertCreated()->json('data');
    $conversation = AgentConversation::query()->findOrFail($session['conversation_id']);
    $conversation->messages()->create(['role' => 'user', 'content' => 'What does resilient mean? Could you add it to my vocabulary?']);

    $toolClient = Mockery::mock(AiToolCallingClient::class);
    $toolClient->shouldReceive('chat')->times(3)->andReturn(
        new AgentChatResponse(null, [new AgentToolCall('context_1', 'get_interview_practice_context', [])]),
        new AgentChatResponse(null, [new AgentToolCall('vocabulary_1', 'propose_interview_vocabulary', [
            'lemma' => 'resilient', 'language' => 'en',
        ])]),
        new AgentChatResponse('I drafted “resilient” as a vocabulary suggestion for you to review.'),
    );
    app()->instance(AiToolCallingClient::class, $toolClient);

    (new RunAgentTurnJob($conversation->id))->handle();

    test()->getJson('/api/me/words?search=resilient')->assertOk()->assertJsonCount(0, 'data');
    $draft = test()->getJson('/api/interview/drafts')->assertOk()->assertJsonPath('data.0.kind', 'vocabulary')
        ->assertJsonPath('data.0.payload.lemma', 'resilient')->json('data.0');
    test()->postJson('/api/interview/drafts/'.$draft['id'].'/confirm')->assertOk()
        ->assertJsonPath('data.result.lemma', 'resilient');
    test()->getJson('/api/me/words?search=resilient')->assertOk()->assertJsonPath('data.0.lexeme', 'resilient');
});
