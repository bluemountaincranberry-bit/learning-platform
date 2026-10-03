<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\Srs\Domain\Models\SrsReview;
use App\Modules\User\Models\User;
use App\Modules\Ai\Application\Agent\Data\AgentChatResponse;
use App\Modules\Ai\Application\Agent\Data\AgentToolCall;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\ReviewAgentService;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\AiToolCallingClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('review agent finds weak words, builds a plan, and proposes a schedule', function () {
    $user = User::factory()->create();
    $content = Content::factory()->create();
    $card = SrsCard::query()->create([
        'user_id' => $user->id, 'content_id' => $content->id, 'item_key' => 'word:struggle',
        'state' => 'reviewing', 'interval_days' => 1, 'ease_factor' => 1.8, 'next_review_at' => now(),
    ]);
    SrsReview::query()->create(['srs_card_id' => $card->id, 'grade' => 1, 'reviewed_at' => now()]);

    $jsonClient = Mockery::mock(AiJsonClient::class);
    $jsonClient->shouldReceive('completeJson')->once()->andReturn([
        'plan' => [['day' => 1, 'items' => ['struggle']]],
    ]);
    app()->instance(AiJsonClient::class, $jsonClient);

    $callCount = 0;
    $toolClient = Mockery::mock(AiToolCallingClient::class);
    $toolClient->shouldReceive('chat')->times(4)->andReturnUsing(function () use (&$callCount) {
        $callCount++;

        return match ($callCount) {
            1 => new AgentChatResponse(null, [new AgentToolCall('call_1', 'get_weak_words', [])]),
            2 => new AgentChatResponse(null, [
                new AgentToolCall('call_2', 'create_review_plan', ['words' => ['struggle']]),
            ]),
            3 => new AgentChatResponse(null, [
                new AgentToolCall('call_3', 'schedule_review', ['plan' => [['day' => 1, 'items' => ['struggle']]]]),
            ]),
            default => new AgentChatResponse('Here is a proposed 1-day review plan focused on "struggle".'),
        };
    });
    app()->instance(AiToolCallingClient::class, $toolClient);

    $result = app(ReviewAgentService::class)->run(
        'Build a review plan for this student.',
        new AgentToolContext(1, $user->id),
        TraceContext::newTrace()
    );

    expect($result)->toBe('Here is a proposed 1-day review plan focused on "struggle".');
});

test('review agent stops gracefully at its iteration limit', function () {
    $toolClient = Mockery::mock(AiToolCallingClient::class);
    $toolClient->shouldReceive('chat')->times(4)->andReturn(
        new AgentChatResponse(null, [new AgentToolCall('call_x', 'get_weak_words', [])])
    );
    app()->instance(AiToolCallingClient::class, $toolClient);

    $result = app(ReviewAgentService::class)->run('plan my review', new AgentToolContext(1, 1), TraceContext::newTrace());

    expect($result)->toContain("couldn't finish");
});

test('review agent blueprint allows read_only and draft_only, never publish', function () {
    expect(ReviewAgentService::blueprint()->allowedSideEffects)->toBe([
        \App\Modules\Ai\Application\Agent\Data\AgentToolDefinition::SIDE_EFFECT_READ_ONLY,
        \App\Modules\Ai\Application\Agent\Data\AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY,
    ]);
});
