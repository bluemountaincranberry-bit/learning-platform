<?php

use App\Exceptions\AiClientException;
use App\Modules\Ai\Domain\Models\Lesson;
use App\Modules\Ai\Domain\Models\LessonAnalysisRun;
use App\Modules\Ai\Application\Agent\Tracing\TracedLlmCall;
use App\Modules\Ai\Application\LessonAnalysisService;
use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\PromptRegistryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

function makeLessonAnalysisService(AiJsonClient $client): LessonAnalysisService
{
    return new LessonAnalysisService($client, app(TracedLlmCall::class), app(PromptRegistryInterface::class));
}

uses(RefreshDatabase::class);

function makeLessonAnalysisRun(string $sourceText = 'We practiced get up and Present Perfect today.'): LessonAnalysisRun
{
    $user = App\Modules\User\Models\User::factory()->create();
    $lesson = Lesson::query()->create([
        'user_id' => $user->id,
        'status' => Lesson::STATUS_ACTIVE,
        'source_text' => $sourceText,
    ]);

    return $lesson->analysisRuns()->create(['status' => LessonAnalysisRun::STATUS_PENDING]);
}

test('analyze persists lexeme and grammar candidates from a valid AI response', function () {
    $run = makeLessonAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')
        ->once()
        ->with(
            Mockery::on(fn ($s) => str_contains($s, 'tutoring session')),
            Mockery::on(fn ($u) => str_contains($u, 'get up')),
            Mockery::type('array'),
            null
        )
        ->andReturn([
            'lexemes' => [
                ['text' => 'get up', 'type' => 'phrasal_verb', 'translation' => 'вставать', 'confidence' => 0.9],
                ['text' => '', 'type' => 'word'], // invalid, must be skipped
            ],
            'grammar' => [
                ['title' => 'Present Perfect', 'summary' => 'unfinished past action', 'confidence' => 0.8],
            ],
        ]);

    makeLessonAnalysisService($client)->analyze($run);

    expect($run->lexemeCandidates()->count())->toBe(1)
        ->and($run->grammarCandidates()->count())->toBe(1);

    $lexeme = $run->lexemeCandidates()->first();
    expect($lexeme->text)->toBe('get up')
        ->and($lexeme->normalized_text)->toBe('get up')
        ->and($lexeme->translation)->toBe('вставать')
        ->and($lexeme->status)->toBe('pending');

    expect($run->grammarCandidates()->first()->title)->toBe('Present Perfect');
});

test('analyze throws when the lesson has no notes yet', function () {
    $run = makeLessonAnalysisRun('');

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldNotReceive('completeJson');

    makeLessonAnalysisService($client)->analyze($run);
})->throws(AiClientException::class, 'no notes');

test('analyze throws when the AI response has no usable candidates', function () {
    $run = makeLessonAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn(['lexemes' => [], 'grammar' => []]);

    makeLessonAnalysisService($client)->analyze($run);
})->throws(AiClientException::class, 'no vocabulary or grammar');

test('analyze dedupes candidates by text/title', function () {
    $run = makeLessonAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [
            ['text' => 'run', 'translation' => 'first wins'],
            ['text' => 'RUN', 'translation' => 'should be ignored'],
        ],
        'grammar' => [],
    ]);

    makeLessonAnalysisService($client)->analyze($run);

    expect($run->lexemeCandidates()->count())->toBe(1)
        ->and($run->lexemeCandidates()->first()->translation)->toBe('first wins');
});

test('analyze drops an invalid CEFR level to null', function () {
    $run = makeLessonAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [['text' => 'word', 'level' => 'not-a-level']],
        'grammar' => [],
    ]);

    makeLessonAnalysisService($client)->analyze($run);

    expect($run->lexemeCandidates()->first()->level)->toBeNull();
});
