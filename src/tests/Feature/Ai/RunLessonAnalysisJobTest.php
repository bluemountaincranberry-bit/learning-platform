<?php

use App\Contracts\Ai\AiJsonClient;
use App\Exceptions\AiClientException;
use App\Modules\Ai\Interfaces\Jobs\RunLessonAnalysisJob;
use App\Modules\Learning\Domain\Models\Lesson;
use App\Modules\Learning\Domain\Models\LessonAnalysisRun;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function queuedLessonRun(): LessonAnalysisRun
{
    config(['queue.connections.database.connection' => 'sqlite']);
    $lesson = Lesson::query()->create([
        'user_id' => User::factory()->create()->id,
        'source_text' => 'We practiced get up and Present Perfect today.',
    ]);
    $run = $lesson->analysisRuns()->create(['status' => LessonAnalysisRun::STATUS_PENDING]);
    Queue::connection('database')->push(new RunLessonAnalysisJob($run->id));

    return $run;
}

function workLessonQueue(): void
{
    app('queue.worker')->runNextJob('database', 'default', new WorkerOptions(sleep: 0));
}

test('same queued lesson analysis succeeds after a transient provider failure', function () {
    $run = queuedLessonRun();
    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andThrow(new AiClientException('Provider unavailable'));
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [['text' => 'get up']], 'grammar' => [['title' => 'Present Perfect']],
    ]);
    app()->instance(AiJsonClient::class, $client);

    workLessonQueue();
    expect($run->fresh()->status)->toBe(LessonAnalysisRun::STATUS_FAILED);
    $this->travel(31)->seconds();
    workLessonQueue();

    expect($run->fresh()->status)->toBe(LessonAnalysisRun::STATUS_COMPLETED)
        ->and($run->fresh()->failure_reason)->toBeNull()
        ->and($run->lexemeCandidates()->count())->toBe(1)
        ->and($run->grammarCandidates()->count())->toBe(1)
        ->and($run->lesson->fresh()->source_text)->toBe('We practiced get up and Present Perfect today.');
});

test('permanent failure exhausts attempts and fresh duplicate deliveries cannot restart the failed run', function () {
    $run = queuedLessonRun();
    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->twice()->andThrow(new AiClientException('Provider unavailable'));
    app()->instance(AiJsonClient::class, $client);

    workLessonQueue();
    $this->travel(31)->seconds();
    workLessonQueue();
    Queue::connection('database')->push(new RunLessonAnalysisJob($run->id));
    workLessonQueue();

    expect($run->fresh()->status)->toBe(LessonAnalysisRun::STATUS_FAILED)
        ->and($run->fresh()->completed_at)->not->toBeNull()
        ->and($run->fresh()->failure_reason)->not->toBeNull()
        ->and($run->lexemeCandidates()->count())->toBe(0)
        ->and($run->lesson->fresh()->source_text)->toBe('We practiced get up and Present Perfect today.')
        ->and(Queue::connection('database')->size())->toBe(0);
});

test('candidate persistence failure rolls back the batch and queued retry creates each candidate once', function () {
    $run = queuedLessonRun();
    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->twice()->andReturn([
        'lexemes' => [['text' => 'get up']], 'grammar' => [['title' => 'Present Perfect']],
    ]);
    app()->instance(AiJsonClient::class, $client);
    $failOnce = true;
    \App\Modules\Learning\Domain\Models\LessonGrammarCandidate::creating(function () use (&$failOnce) {
        if ($failOnce) {
            $failOnce = false;
            throw new RuntimeException('Simulated interrupted candidate persistence');
        }
    });

    try {
        workLessonQueue();
        expect($run->lexemeCandidates()->count())->toBe(0)
            ->and($run->grammarCandidates()->count())->toBe(0);
        $this->travel(31)->seconds();
        workLessonQueue();
        Queue::connection('database')->push(new RunLessonAnalysisJob($run->id));
        workLessonQueue();

        expect($run->fresh()->status)->toBe(LessonAnalysisRun::STATUS_COMPLETED)
            ->and($run->lexemeCandidates()->count())->toBe(1)
            ->and($run->grammarCandidates()->count())->toBe(1);
    } finally {
        \App\Modules\Learning\Domain\Models\LessonGrammarCandidate::flushEventListeners();
    }
});

test('retry retains previously persisted candidates and ignores delivery while running', function () {
    $run = queuedLessonRun();
    $run->update(['status' => LessonAnalysisRun::STATUS_FAILED]);
    $existing = $run->lexemeCandidates()->create([
        'text' => 'get up', 'normalized_text' => 'get up', 'type' => 'phrasal_verb',
        'status' => 'new', 'translation' => 'existing translation',
    ]);
    // An actual retry payload has already used its first attempt.
    \Illuminate\Support\Facades\DB::table('jobs')->update(['attempts' => 1]);
    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturnUsing(function () use ($run) {
        app()->call([new RunLessonAnalysisJob($run->id), 'handle']);

        return ['lexemes' => [['text' => 'GET UP']], 'grammar' => [['title' => 'Present Perfect']]];
    });
    app()->instance(AiJsonClient::class, $client);
    workLessonQueue();

    expect($run->fresh()->status)->toBe(LessonAnalysisRun::STATUS_COMPLETED)
        ->and($run->lexemeCandidates()->count())->toBe(1)
        ->and($run->lexemeCandidates()->first()->id)->toBe($existing->id)
        ->and($existing->fresh()->translation)->toBe('existing translation')
        ->and($run->grammarCandidates()->count())->toBe(1);
});
