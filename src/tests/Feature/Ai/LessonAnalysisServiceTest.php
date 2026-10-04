<?php

use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\PromptRegistryInterface;
use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\Agent\Tracing\TracedLlmCall;
use App\Modules\Ai\Application\LessonAnalysisService;
use App\Modules\Learning\Domain\Models\Lesson;
use App\Modules\Learning\Domain\Models\LessonAnalysisRun;
use Illuminate\Foundation\Testing\RefreshDatabase;

function makeLessonAnalysisService(AiJsonClient $client): LessonAnalysisService
{
    return new LessonAnalysisService($client, app(TracedLlmCall::class), app(PromptRegistryInterface::class), app(\App\Contracts\Ai\LessonAnalysisStoreInterface::class));
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
    $client->shouldReceive('completeJson')->once()
        ->with(Mockery::any(), Mockery::on(fn ($u) => str_contains($u, 'ALREADY EXTRACTED')), Mockery::type('array'), null)
        ->andReturn(['lexemes' => []]);
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

    makeLessonAnalysisService($client)->analyze($run->id);

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

    makeLessonAnalysisService($client)->analyze($run->id);
})->throws(AiClientException::class, 'no notes');

test('analyze throws when the AI response has no usable candidates', function () {
    $run = makeLessonAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->twice()  ->andReturn(['lexemes' => [], 'grammar' => []]);

    makeLessonAnalysisService($client)->analyze($run->id);
})->throws(AiClientException::class, 'no vocabulary or grammar');

test('analyze dedupes candidates by text/title', function () {
    $run = makeLessonAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->twice()  ->andReturn([
        'lexemes' => [
            ['text' => 'run', 'translation' => 'first wins'],
            ['text' => 'RUN', 'translation' => 'should be ignored'],
        ],
        'grammar' => [],
    ]);

    makeLessonAnalysisService($client)->analyze($run->id);

    expect($run->lexemeCandidates()->count())->toBe(1)
        ->and($run->lexemeCandidates()->first()->translation)->toBe('first wins');
});

test('analyze drops an invalid CEFR level to null', function () {
    $run = makeLessonAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->twice()  ->andReturn([
        'lexemes' => [['text' => 'word', 'level' => 'not-a-level']],
        'grammar' => [],
    ]);

    makeLessonAnalysisService($client)->analyze($run->id);

    expect($run->lexemeCandidates()->first()->level)->toBeNull();
});

test('analyze sends every part of a long lesson to the model and merges the results', function () {
    config(['ai.analysis.lesson_chunk_chars' => 300]);

    $lines = array_map(fn ($i) => "item{$i} is a phrase to learn", range(1, 60));
    $run = makeLessonAnalysisRun(implode("\n", $lines));

    $client = Mockery::mock(AiJsonClient::class);
    $seen = '';
    $client->shouldReceive('completeJson')
        ->atLeast()->times(2)
        ->andReturnUsing(function ($system, $user) use (&$seen) {
            $seen .= "\n".$user;
            preg_match_all('/item(\d+)/', $user, $m);

            return [
                'lexemes' => array_map(fn ($n) => ['text' => "item{$n}", 'translation' => 'x'], $m[1]),
                'grammar' => [],
            ];
        });

    makeLessonAnalysisService($client)->analyze($run->id);

    expect($seen)->toContain('item1 ')->toContain('item60 ')
        ->and($run->lexemeCandidates()->count())->toBe(60);
});

test('analyze keeps going when one part of a long lesson finds nothing', function () {
    config(['ai.analysis.lesson_chunk_chars' => 100]);
    $run = makeLessonAnalysisRun(str_repeat('some filler words here ', 20)."\nto encourage smn to do smth");

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->andReturnUsing(function ($system, $user) {
        return str_contains($user, 'encourage')
            ? ['lexemes' => [['text' => 'to encourage smn to do smth']], 'grammar' => []]
            : ['lexemes' => [], 'grammar' => []];
    });

    makeLessonAnalysisService($client)->analyze($run->id);

    expect($run->lexemeCandidates()->pluck('text')->all())->toBe(['to encourage smn to do smth']);
});

test('analyze treats curly/straight apostrophes and trailing punctuation as the same item', function () {
    $run = makeLessonAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->twice()  ->andReturn([
        'lexemes' => [
            ['text' => 'You’ve got a point.', 'translation' => 'first'],
            ['text' => "You've got a point", 'translation' => 'dup'],
            ['text' => 'On balance, …', 'translation' => 'second'],
            ['text' => 'On balance,', 'translation' => 'dup'],
        ],
        'grammar' => [],
    ]);

    makeLessonAnalysisService($client)->analyze($run->id);

    expect($run->lexemeCandidates()->pluck('translation')->all())->toBe(['first', 'second']);
});

test('analyze adds the glossed expressions found by the bonus pass', function () {
    $run = makeLessonAnalysisRun('Her doctor warned of heavy physical exertion (тяжёлая нагрузка).');

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->andReturnUsing(function ($system, $user) {
        if (str_contains($user, 'ALREADY EXTRACTED')) {
            expect($user)->toContain('- heavy load');

            return ['lexemes' => [['text' => 'exertion', 'note' => 'from the example', 'confidence' => 0.8]]];
        }

        return ['lexemes' => [['text' => 'heavy load']], 'grammar' => []];
    });

    makeLessonAnalysisService($client)->analyze($run->id);

    expect($run->lexemeCandidates()->orderBy('id')->pluck('text')->all())->toBe(['heavy load', 'exertion']);
});

test('analyze keeps the main list when the bonus pass fails', function () {
    $run = makeLessonAnalysisRun();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->andReturnUsing(function ($system, $user) {
        if (str_contains($user, 'ALREADY EXTRACTED')) {
            throw new AiClientException('timeout');
        }

        return ['lexemes' => [['text' => 'get up']], 'grammar' => []];
    });

    makeLessonAnalysisService($client)->analyze($run->id);

    expect($run->lexemeCandidates()->pluck('text')->all())->toBe(['get up']);
});
