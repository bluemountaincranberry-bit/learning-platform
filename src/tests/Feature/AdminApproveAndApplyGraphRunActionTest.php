<?php

use App\Filament\Pages\AgentGraphRuns;
use App\Modules\Ai\Domain\Models\AgentGraphRun;
use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexemeCandidate;
use App\Modules\User\Models\User;
use App\Modules\Ai\Application\Agent\Graph\AiAnalysisGraphService;
use App\Contracts\Ai\AiJsonClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAdminForGraphRuns(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::firstOrCreate(
        ['email' => 'admin-graph-runs@example.com'],
        ['name' => 'Admin User', 'password' => bcrypt('password')]
    );
    if (! $admin->hasRole('admin')) {
        $admin->assignRole('admin');
    }

    test()->actingAs($admin, 'web');

    return $admin;
}

function pausedGraphRunFixture(): array
{
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Graph runs page fixture',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
        'source_text' => 'I have been waiting for you to get up all morning.',
    ]);
    $run = $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_PENDING]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'lexemes' => [['text' => 'get up', 'type' => 'phrasal_verb', 'translation' => 'вставать']],
        'grammar' => [],
    ]);
    app()->instance(AiJsonClient::class, $client);

    $service = app(AiAnalysisGraphService::class);
    $service->start($run);
    $dbRun = $service->latestRunFor($run);

    return [$run, $dbRun];
}

test('canApproveAndApply is true only for a paused ai_analysis run at review_checkpoint', function () {
    actingAdminForGraphRuns();
    [$run, $dbRun] = pausedGraphRunFixture();

    $page = new AgentGraphRuns;

    expect($page->canApproveAndApply($dbRun))->toBeTrue();

    $dbRun->status = AgentGraphRun::STATUS_COMPLETED;
    expect($page->canApproveAndApply($dbRun))->toBeFalse();

    $dbRun->status = AgentGraphRun::STATUS_PAUSED;
    $dbRun->current_node = 'apply';
    expect($page->canApproveAndApply($dbRun))->toBeFalse();

    $dbRun->current_node = 'review_checkpoint';
    $dbRun->graph_name = 'tutor_routing';
    expect($page->canApproveAndApply($dbRun))->toBeFalse();
});

test('clicking Approve & Apply resumes the graph run and applies accepted candidates', function () {
    actingAdminForGraphRuns();
    [$run, $dbRun] = pausedGraphRunFixture();

    $run->lexemeCandidates()->update(['status' => ContentLexemeCandidate::STATUS_ACCEPTED]);

    Livewire::test(AgentGraphRuns::class)
        ->assertOk()
        ->callAction('approveAndApply', arguments: ['graphRunId' => $dbRun->id]);

    $dbRun->refresh();
    expect($dbRun->status)->toBe(AgentGraphRun::STATUS_COMPLETED);

    $run->refresh();
    expect($run->lexemeCandidates()->first()->status)->toBe(ContentLexemeCandidate::STATUS_APPLIED);
});
