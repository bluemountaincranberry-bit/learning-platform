<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\User\Models\User;
use App\Modules\Ai\Application\Agent\Data\AgentChatResponse;
use App\Modules\Ai\Application\Agent\Data\AgentToolCall;
use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\AiToolCallingClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Task 5.1: this suite exercises AiEvalCommand's own mechanics (fixture
 * loading, scoring, exit codes, rollback) with faked clients — fast and
 * CI-safe. The command was additionally run for real against the live
 * OpenAI provider during development (see the task's final report) to
 * confirm the harness produces a meaningful signal on real model output,
 * not just against these fakes.
 */
beforeEach(function () {
    config(['ai.enabled' => true, 'ai.openai.api_key' => 'test-key']);
});

test('content_analysis suite reports full recall when the AI response covers the golden dataset, and leaves no rows behind', function () {
    $usersBefore = User::query()->count();
    $contentBefore = Content::query()->count();

    $jsonClient = Mockery::mock(AiJsonClient::class);
    $jsonClient->shouldReceive('completeJson')->andReturn([
        'lexemes' => [
            ['text' => 'negotiate', 'type' => 'word', 'translation' => 'т', 'example' => 'e', 'confidence' => 0.9],
            ['text' => 'deadline', 'type' => 'word', 'translation' => 'т', 'example' => 'e', 'confidence' => 0.9],
            ['text' => 'collaborate', 'type' => 'word', 'translation' => 'т', 'example' => 'e', 'confidence' => 0.9],
            ['text' => 'postpone', 'type' => 'word', 'translation' => 'т', 'example' => 'e', 'confidence' => 0.9],
            ['text' => 'bump into', 'type' => 'phrasal_verb', 'translation' => 'т', 'example' => 'e', 'confidence' => 0.9],
            ['text' => 'end up', 'type' => 'phrasal_verb', 'translation' => 'т', 'example' => 'e', 'confidence' => 0.9],
            ['text' => 'accommodation', 'type' => 'word', 'translation' => 'т', 'example' => 'e', 'confidence' => 0.9],
            ['text' => 'in advance', 'type' => 'phrase', 'translation' => 'т', 'example' => 'e', 'confidence' => 0.9],
        ],
        'grammar' => [
            ['title' => 'Present Perfect', 'summary' => 's', 'example' => 'e', 'confidence' => 0.9],
            ['title' => 'Past Simple', 'summary' => 's', 'example' => 'e', 'confidence' => 0.9],
            ['title' => 'Second Conditional', 'summary' => 's', 'example' => 'e', 'confidence' => 0.9],
        ],
    ]);
    app()->instance(AiJsonClient::class, $jsonClient);

    $this->artisan('ai:eval', ['--suite' => 'content_analysis'])
        ->expectsTable(
            ['case', 'lexemes matched', 'grammar matched', 'recall'],
            [
                ['business-negotiation', '4/4', '1/1', '100%'],
                ['daily-routine-past-simple', '2/2', '1/1', '100%'],
                ['travel-conditionals', '2/2', '1/1', '100%'],
            ]
        )
        ->assertExitCode(0);

    expect(User::query()->count())->toBe($usersBefore)
        ->and(Content::query()->count())->toBe($contentBefore);
});

test('content_analysis suite fails when recall drops below the threshold', function () {
    $jsonClient = Mockery::mock(AiJsonClient::class);
    $jsonClient->shouldReceive('completeJson')->andReturn([
        'lexemes' => [],
        'grammar' => [],
    ]);
    app()->instance(AiJsonClient::class, $jsonClient);

    $this->artisan('ai:eval', ['--suite' => 'content_analysis'])
        ->assertExitCode(1);
});

test('tutor_agent suite reports which tool the model called and leaves no rows behind', function () {
    $usersBefore = User::query()->count();

    $toolClient = Mockery::mock(AiToolCallingClient::class);
    $toolClient->shouldReceive('chat')->andReturnUsing(function (array $messages) {
        $lastToolMessage = collect($messages)->last(fn (array $m) => $m['role'] === 'tool');
        if ($lastToolMessage !== null) {
            return new AgentChatResponse('You are around b1 level, your review is due, and the present perfect explains completed actions with present relevance.');
        }

        $userMessage = collect($messages)->firstWhere('role', 'user')['content'] ?? '';

        return match (true) {
            str_contains($userMessage, 'CEFR level') => new AgentChatResponse(null, [new AgentToolCall('call_1', 'get_user_level', [])]),
            str_contains($userMessage, 'due for review') => new AgentChatResponse(null, [new AgentToolCall('call_1', 'get_review_schedule', [])]),
            default => new AgentChatResponse(null, [new AgentToolCall('call_1', 'explain_grammar', ['topic' => 'present perfect'])]),
        };
    });
    app()->instance(AiToolCallingClient::class, $toolClient);

    $this->artisan('ai:eval', ['--suite' => 'tutor_agent'])
        ->expectsTable(
            ['case', 'tools called', 'expected tool called', 'keyword hits'],
            [
                ['level-question', 'get_user_level', 'yes', '2/2'],
                ['review-schedule-question', 'get_review_schedule', 'yes', '2/2'],
                ['grammar-explanation-question', 'explain_grammar', 'yes', '1/1'],
            ]
        )
        ->assertExitCode(0);

    expect(User::query()->count())->toBe($usersBefore);
});

test('tutor_agent suite fails when the model never calls the expected grounding tool', function () {
    $toolClient = Mockery::mock(AiToolCallingClient::class);
    $toolClient->shouldReceive('chat')->andReturn(new AgentChatResponse('I think you are probably around B1, based on general experience.'));
    app()->instance(AiToolCallingClient::class, $toolClient);

    $this->artisan('ai:eval', ['--suite' => 'tutor_agent'])
        ->assertExitCode(1);
});

test('command fails fast when AI is disabled', function () {
    config(['ai.enabled' => false]);

    $this->artisan('ai:eval', ['--suite' => 'all'])
        ->assertExitCode(1);
});

test('command fails on an unknown suite', function () {
    $this->artisan('ai:eval', ['--suite' => 'nonsense'])
        ->assertExitCode(1);
});
