<?php

use App\Filament\Resources\Contents\Pages\ViewContent;
use App\Modules\Ai\Interfaces\Jobs\RunAiAnalysisGraphJob;
use App\Modules\Ai\Interfaces\Jobs\RunAiContentAnalysisJob;
use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Task 8.4 — the beta "Analyze via graph engine" action, parallel to the
 * live "Analyze with AI" (see AdminAnalyzeWithAiActionTest for that
 * action's own coverage of the shared AiAnalysisRunConfig plumbing, which
 * this action reuses unchanged).
 */
function actingAdminForGraphAnalyze(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::firstOrCreate(
        ['email' => 'admin-ai-graph-analyze@example.com'],
        ['name' => 'Admin User', 'password' => bcrypt('password')]
    );
    if (! $admin->hasRole('admin')) {
        $admin->assignRole('admin');
    }

    test()->actingAs($admin, 'web');

    return $admin;
}

test('clicking the graph analyze action creates a run and queues RunAiAnalysisGraphJob', function () {
    config(['ai.enabled' => true]);
    Queue::fake();

    $admin = actingAdminForGraphAnalyze();
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'Graph analyze test', 'language' => 'en',
        'origin' => 'curated', 'status' => 'ready', 'source_text' => 'Some transcript text.', 'created_by' => $admin->id,
    ]);

    Livewire::test(ViewContent::class, ['record' => $content->getRouteKey()])
        ->callAction('analyzeWithAiGraph', data: [
            'exclude_known_words' => false,
        ]);

    $run = $content->fresh()->latestAnalysisRun;
    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(AiAnalysisRun::STATUS_PENDING);

    Queue::assertPushed(RunAiAnalysisGraphJob::class, fn (RunAiAnalysisGraphJob $job): bool => $job->runId === $run->id);
    Queue::assertNotPushed(RunAiContentAnalysisJob::class);
});

test('the graph analyze action creates its own run without disturbing an existing live-path run', function () {
    config(['ai.enabled' => true]);
    Queue::fake();

    $admin = actingAdminForGraphAnalyze();
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'Side by side test', 'language' => 'en',
        'origin' => 'curated', 'status' => 'ready', 'source_text' => 'Some transcript text.', 'created_by' => $admin->id,
    ]);

    $liveRun = $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_COMPLETED]);

    Livewire::test(ViewContent::class, ['record' => $content->getRouteKey()])
        ->callAction('analyzeWithAiGraph', data: ['exclude_known_words' => false]);

    expect($content->analysisRuns()->count())->toBe(2);

    $liveRun->refresh();
    expect($liveRun->status)->toBe(AiAnalysisRun::STATUS_COMPLETED);
});

test('the graph analyze action is hidden without a transcript', function () {
    config(['ai.enabled' => true]);

    $admin = actingAdminForGraphAnalyze();
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'No transcript test', 'language' => 'en',
        'origin' => 'curated', 'status' => 'ready', 'source_text' => null, 'created_by' => $admin->id,
    ]);

    Livewire::test(ViewContent::class, ['record' => $content->getRouteKey()])
        ->assertOk()
        ->assertActionHidden('analyzeWithAiGraph');
});

test('the graph analyze action is hidden when ai is disabled', function () {
    config(['ai.enabled' => false]);

    $admin = actingAdminForGraphAnalyze();
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'AI disabled test', 'language' => 'en',
        'origin' => 'curated', 'status' => 'ready', 'source_text' => 'Some transcript text.', 'created_by' => $admin->id,
    ]);

    Livewire::test(ViewContent::class, ['record' => $content->getRouteKey()])
        ->assertActionHidden('analyzeWithAiGraph');
});
