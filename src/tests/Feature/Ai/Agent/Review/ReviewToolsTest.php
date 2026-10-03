<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\Srs\Domain\Models\SrsReview;
use App\Modules\User\Models\User;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\Agent\Tools\Review\CreateReviewPlanTool;
use App\Modules\Ai\Application\Agent\Tools\Review\GetWeakWordsTool;
use App\Modules\Ai\Application\Agent\Tools\Review\ScheduleReviewTool;
use App\Contracts\Ai\AiJsonClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('sideEffects: get_weak_words is read_only, the other two are draft_only', function () {
    expect(app(GetWeakWordsTool::class)->definition()->sideEffect)->toBe(AgentToolDefinition::SIDE_EFFECT_READ_ONLY)
        ->and(app(CreateReviewPlanTool::class)->definition()->sideEffect)->toBe(AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY)
        ->and((new ScheduleReviewTool)->definition()->sideEffect)->toBe(AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY);
});

// --- GetWeakWordsTool ---------------------------------------------------

function reviewToolsFixtureCard(int $userId, string $itemKey, float $easeFactor): SrsCard
{
    $content = Content::factory()->create();

    return SrsCard::query()->create([
        'user_id' => $userId,
        'content_id' => $content->id,
        'item_key' => $itemKey,
        'state' => 'reviewing',
        'interval_days' => 1,
        'ease_factor' => $easeFactor,
        'next_review_at' => now(),
    ]);
}

test('get weak words ranks cards by fail rate, weakest first', function () {
    $user = User::factory()->create();

    $weak = reviewToolsFixtureCard($user->id, 'word:struggle', 1.8);
    SrsReview::query()->create(['srs_card_id' => $weak->id, 'grade' => 1, 'reviewed_at' => now()]);
    SrsReview::query()->create(['srs_card_id' => $weak->id, 'grade' => 2, 'reviewed_at' => now()]);

    $strong = reviewToolsFixtureCard($user->id, 'word:easy', 2.6);
    SrsReview::query()->create(['srs_card_id' => $strong->id, 'grade' => 4, 'reviewed_at' => now()]);

    $result = (new GetWeakWordsTool)->execute([], new AgentToolContext(1, $user->id));

    expect($result['weak_words'])->toHaveCount(2)
        ->and($result['weak_words'][0]['item'])->toBe('struggle')
        ->and($result['weak_words'][0]['fail_rate'])->toBe(1.0)
        ->and($result['weak_words'][1]['item'])->toBe('easy');
});

test('get weak words reports no data yet when the user has no review history', function () {
    $user = User::factory()->create();

    $result = (new GetWeakWordsTool)->execute([], new AgentToolContext(1, $user->id));

    expect($result['weak_words'])->toBe([])->and($result)->toHaveKey('note');
});

test('get weak words only counts the acting user\'s own cards', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $card = reviewToolsFixtureCard($otherUser->id, 'word:notmine', 2.5);
    SrsReview::query()->create(['srs_card_id' => $card->id, 'grade' => 1, 'reviewed_at' => now()]);

    $result = (new GetWeakWordsTool)->execute([], new AgentToolContext(1, $user->id));

    expect($result['weak_words'])->toBe([]);
});

// --- CreateReviewPlanTool ------------------------------------------------

test('create review plan groups words into a sanitized day-by-day structure', function () {
    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'plan' => [
            ['day' => 1, 'items' => ['run', 'give up']],
            ['day' => 2, 'items' => ['struggle']],
            ['day' => 'not-a-day', 'items' => ['ignored']],
        ],
    ]);
    app()->instance(AiJsonClient::class, $client);

    $result = app(CreateReviewPlanTool::class)->execute(['words' => ['run', 'give up', 'struggle']], new AgentToolContext(1, 1));

    expect($result['plan'])->toBe([
        ['day' => 1, 'items' => ['run', 'give up']],
        ['day' => 2, 'items' => ['struggle']],
    ])->and($result['is_draft'])->toBeTrue();
});

test('create review plan requires non-empty words', function () {
    $result = app(CreateReviewPlanTool::class)->execute([], new AgentToolContext(1, 1));

    expect($result)->toHaveKey('error');
});

// --- ScheduleReviewTool --------------------------------------------------

test('schedule review converts day numbers into proposed calendar dates without persisting anything', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-08'));

    $result = (new ScheduleReviewTool)->execute([
        'plan' => [
            ['day' => 1, 'items' => ['run']],
            ['day' => 3, 'items' => ['give up', 'struggle']],
        ],
    ], new AgentToolContext(1, 1));

    expect($result['schedule'])->toBe([
        ['item' => 'run', 'proposed_review_date' => '2026-08-08'],
        ['item' => 'give up', 'proposed_review_date' => '2026-08-10'],
        ['item' => 'struggle', 'proposed_review_date' => '2026-08-10'],
    ])->and($result['is_draft'])->toBeTrue();

    Carbon::setTestNow();
});

test('schedule review requires a non-empty plan', function () {
    $result = (new ScheduleReviewTool)->execute([], new AgentToolContext(1, 1));

    expect($result)->toHaveKey('error');
});
