<?php

use App\Modules\Ai\Interfaces\Jobs\RunAiAnalysisGraphJob;
use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Domain\Models\Content;
use App\Contracts\Ai\AiJsonClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

function graphJobFixtureContent(): Content
{
    return Content::query()->create([
        'type' => 'youtube',
        'title' => 'Test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
        'source_text' => 'Some transcript text.',
    ]);
}

test('dispatching the job queues it with the correct run id', function () {
    Bus::fake();

    $run = graphJobFixtureContent()->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_PENDING]);

    RunAiAnalysisGraphJob::dispatch($run->id);

    Bus::assertDispatched(RunAiAnalysisGraphJob::class, fn (RunAiAnalysisGraphJob $job): bool => $job->runId === $run->id);
});

test('job calls AiAnalysisGraphService::start() and the run ends up completed', function () {
    $run = graphJobFixtureContent()->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_PENDING]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [['text' => 'text', 'type' => 'word']],
        'grammar' => [],
    ]);
    $this->app->instance(AiJsonClient::class, $client);

    app()->call([new RunAiAnalysisGraphJob($run->id), 'handle']);

    $run->refresh();
    // Task 8.2's start() drove this to completed (paused at review_checkpoint) —
    // proves the job actually invoked start(), not just that it didn't error.
    expect($run->status)->toBe(AiAnalysisRun::STATUS_COMPLETED)
        ->and($run->lexemeCandidates()->count())->toBe(1);
});

test('job does nothing when run is not pending', function () {
    $run = graphJobFixtureContent()->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_COMPLETED]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldNotReceive('completeJson');
    $this->app->instance(AiJsonClient::class, $client);

    app()->call([new RunAiAnalysisGraphJob($run->id), 'handle']);

    $run->refresh();
    expect($run->status)->toBe(AiAnalysisRun::STATUS_COMPLETED);
});

test('job does nothing when run does not exist', function () {
    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldNotReceive('completeJson');
    $this->app->instance(AiJsonClient::class, $client);

    app()->call([new RunAiAnalysisGraphJob(999999), 'handle']);

    expect(true)->toBeTrue();
});
