<?php

use App\Exceptions\AgentToolException;
use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\Agent\Tools\Student\GenerateQuizTool;
use App\Contracts\Ai\AiJsonClient;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Srs\Application\Contracts\ReviewScheduleReaderInterface;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sideEffect is draft_only for GenerateQuizTool', function () {
    $client = Mockery::mock(AiJsonClient::class);
    expect((new GenerateQuizTool($client, app(ReviewScheduleReaderInterface::class)))->definition()->sideEffect)->toBe(AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY);
});

test('generate quiz returns a valid, sanitized question list for explicit words', function () {
    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'questions' => [
            ['type' => 'multiple_choice', 'prompt' => 'What does "run" mean?', 'choices' => ['to move fast', 'to sleep', 'to eat'], 'answer' => 'to move fast'],
            ['type' => 'gap_fill', 'prompt' => 'I ___ every morning.', 'answer' => 'run'],
            ['type' => 'multiple_choice', 'prompt' => 'Bad question, only one choice', 'choices' => ['x'], 'answer' => 'x'],
            ['type' => 'unknown_type', 'prompt' => 'Invalid type', 'answer' => 'x'],
            ['type' => 'gap_fill', 'prompt' => '', 'answer' => 'x'],
        ],
    ]);

    $result = (new GenerateQuizTool($client, app(ReviewScheduleReaderInterface::class)))->execute(['words' => ['run']], new AgentToolContext(1, 1));

    expect($result['quiz'])->toHaveCount(2)
        ->and($result['quiz'][0]['type'])->toBe('multiple_choice')
        ->and($result['quiz'][0]['choices'])->toBe(['to move fast', 'to sleep', 'to eat'])
        ->and($result['quiz'][1]['type'])->toBe('gap_fill')
        ->and($result['is_draft'])->toBeTrue();
});

test('generate quiz defaults to the student\'s due review words when none are given', function () {
    $user = User::factory()->create();
    $content = Content::factory()->create();
    SrsCard::query()->create([
        'user_id' => $user->id, 'content_id' => $content->id, 'item_key' => 'word:apple',
        'state' => 'reviewing', 'interval_days' => 1, 'ease_factor' => 2.5, 'next_review_at' => now(),
    ]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')
        ->once()
        ->withArgs(fn ($system, $user, $schema) => str_contains($user, 'apple'))
        ->andReturn(['questions' => []]);

    (new GenerateQuizTool($client, app(ReviewScheduleReaderInterface::class)))->execute([], new AgentToolContext(1, $user->id));
});

test('generate quiz reports no data when the student has no words and none were given', function () {
    $user = User::factory()->create();
    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldNotReceive('completeJson');

    $result = (new GenerateQuizTool($client, app(ReviewScheduleReaderInterface::class)))->execute([], new AgentToolContext(1, $user->id));

    expect($result['quiz'])->toBe([])->and($result)->toHaveKey('note');
});

test('generate quiz never writes to user_lexeme_progress or any other live state', function () {
    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn(['questions' => []]);

    (new GenerateQuizTool($client, app(ReviewScheduleReaderInterface::class)))->execute(['words' => ['run']], new AgentToolContext(1, 1));

    expect(\App\Modules\Learning\Domain\Models\UserLexemeProgress::query()->count())->toBe(0);
});

test('generate quiz surfaces an AI client failure as an AgentToolException', function () {
    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andThrow(new AiClientException('boom'));

    (new GenerateQuizTool($client, app(ReviewScheduleReaderInterface::class)))->execute(['words' => ['run']], new AgentToolContext(1, 1));
})->throws(AgentToolException::class);

test('generate quiz respects the requested count within bounds', function () {
    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')
        ->once()
        ->withArgs(fn ($system) => str_contains($system, 'exactly 10 '))
        ->andReturn(['questions' => []]);

    (new GenerateQuizTool($client, app(ReviewScheduleReaderInterface::class)))->execute(['words' => ['run'], 'count' => 999], new AgentToolContext(1, 1));
});
