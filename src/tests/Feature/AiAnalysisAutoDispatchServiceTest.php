<?php

use App\Modules\Ai\Interfaces\Jobs\RunAiContentAnalysisJob;
use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\User\Models\User;
use App\Modules\Content\Application\AiAnalysisAutoDispatchService;
use Illuminate\Support\Facades\Queue;

function makeContentForAutoDispatch(array $overrides = []): Content
{
    return Content::query()->create(array_merge([
        'type' => 'youtube',
        'title' => 'Auto dispatch test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
        'source_text' => 'Some transcript text.',
    ], $overrides));
}

test('does nothing when the AI feature is disabled', function () {
    config(['ai.enabled' => false]);
    Queue::fake();

    $content = makeContentForAutoDispatch();
    app(AiAnalysisAutoDispatchService::class)->dispatchFor($content);

    Queue::assertNotPushed(RunAiContentAnalysisJob::class);
    expect($content->fresh()->latestAnalysisRun)->toBeNull();
});

test('does nothing when content has no transcript', function () {
    config(['ai.enabled' => true]);
    Queue::fake();

    $content = makeContentForAutoDispatch(['source_text' => null]);
    app(AiAnalysisAutoDispatchService::class)->dispatchFor($content);

    Queue::assertNotPushed(RunAiContentAnalysisJob::class);
});

test('curated content dispatches an analysis run automatically with no quota', function () {
    config(['ai.enabled' => true, 'ai.rate_limits.user_submission_analysis_per_day' => 0]);
    Queue::fake();

    $content = makeContentForAutoDispatch(['origin' => 'curated']);
    app(AiAnalysisAutoDispatchService::class)->dispatchFor($content);

    $run = $content->fresh()->latestAnalysisRun;
    expect($run)->not->toBeNull();
    expect($run->status)->toBe(AiAnalysisRun::STATUS_PENDING);
    Queue::assertPushed(RunAiContentAnalysisJob::class, fn ($job) => $job->runId === $run->id);
});

test('does not dispatch again while an analysis run is already pending or running', function () {
    config(['ai.enabled' => true]);
    Queue::fake();

    $content = makeContentForAutoDispatch(['origin' => 'curated']);
    $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_RUNNING, 'config' => []]);

    app(AiAnalysisAutoDispatchService::class)->dispatchFor($content);

    Queue::assertNotPushed(RunAiContentAnalysisJob::class);
});

test('user-submitted content dispatches while under the daily quota and consumes it', function () {
    config(['ai.enabled' => true, 'ai.rate_limits.user_submission_analysis_per_day' => 1]);
    Queue::fake();

    $user = User::factory()->create();
    $content = makeContentForAutoDispatch(['origin' => 'user-submitted', 'created_by' => $user->id]);

    app(AiAnalysisAutoDispatchService::class)->dispatchFor($content);

    Queue::assertPushed(RunAiContentAnalysisJob::class);
    expect($content->fresh()->latestAnalysisRun)->not->toBeNull();
});

test('user-submitted content stops dispatching once the daily quota is exhausted', function () {
    config(['ai.enabled' => true, 'ai.rate_limits.user_submission_analysis_per_day' => 1]);
    Queue::fake();

    $user = User::factory()->create();
    $first = makeContentForAutoDispatch(['origin' => 'user-submitted', 'created_by' => $user->id]);
    $second = makeContentForAutoDispatch(['origin' => 'user-submitted', 'created_by' => $user->id]);

    $service = app(AiAnalysisAutoDispatchService::class);
    $service->dispatchFor($first);
    $service->dispatchFor($second);

    expect($first->fresh()->latestAnalysisRun)->not->toBeNull();
    expect($second->fresh()->latestAnalysisRun)->toBeNull();
    Queue::assertPushed(RunAiContentAnalysisJob::class, 1);
});

test('user-submitted content never dispatches when the quota is set to zero', function () {
    config(['ai.enabled' => true, 'ai.rate_limits.user_submission_analysis_per_day' => 0]);
    Queue::fake();

    $user = User::factory()->create();
    $content = makeContentForAutoDispatch(['origin' => 'user-submitted', 'created_by' => $user->id]);

    app(AiAnalysisAutoDispatchService::class)->dispatchFor($content);

    Queue::assertNotPushed(RunAiContentAnalysisJob::class);
});

test('falls back to the submitter current_level when content has no curated level', function () {
    config(['ai.enabled' => true, 'ai.rate_limits.user_submission_analysis_per_day' => 1]);
    Queue::fake();

    $user = User::factory()->create(['current_level' => 'B1']);
    $content = makeContentForAutoDispatch(['origin' => 'user-submitted', 'created_by' => $user->id, 'level' => null]);

    app(AiAnalysisAutoDispatchService::class)->dispatchFor($content);

    expect($content->fresh()->latestAnalysisRun->config['target_level'])->toBe('B1');
});

test('curated content level always wins over the submitter current_level', function () {
    config(['ai.enabled' => true, 'ai.rate_limits.user_submission_analysis_per_day' => 1]);
    Queue::fake();

    $user = User::factory()->create(['current_level' => 'A1']);
    $content = makeContentForAutoDispatch(['origin' => 'user-submitted', 'created_by' => $user->id, 'level' => 'C2']);

    app(AiAnalysisAutoDispatchService::class)->dispatchFor($content);

    expect($content->fresh()->latestAnalysisRun->config['target_level'])->toBe('C2');
});

test('defaults to thorough when the submitter has no ai_extraction_thoroughness preference', function () {
    config(['ai.enabled' => true, 'ai.rate_limits.user_submission_analysis_per_day' => 1]);
    Queue::fake();

    $user = User::factory()->create(['ai_extraction_thoroughness' => null]);
    $content = makeContentForAutoDispatch(['origin' => 'user-submitted', 'created_by' => $user->id]);

    app(AiAnalysisAutoDispatchService::class)->dispatchFor($content);

    expect($content->fresh()->latestAnalysisRun->config['thoroughness'])->toBe('thorough');
});

test('respects the submitter own ai_extraction_thoroughness preference', function () {
    config(['ai.enabled' => true, 'ai.rate_limits.user_submission_analysis_per_day' => 1]);
    Queue::fake();

    $user = User::factory()->create(['ai_extraction_thoroughness' => 'focused']);
    $content = makeContentForAutoDispatch(['origin' => 'user-submitted', 'created_by' => $user->id]);

    app(AiAnalysisAutoDispatchService::class)->dispatchFor($content);

    expect($content->fresh()->latestAnalysisRun->config['thoroughness'])->toBe('focused');
});
