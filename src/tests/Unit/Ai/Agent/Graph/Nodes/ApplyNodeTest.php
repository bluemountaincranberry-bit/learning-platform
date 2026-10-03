<?php

use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Ai\Application\Agent\Graph\GraphState;
use App\Modules\Ai\Application\Agent\Graph\Nodes\ApplyNode;
use App\Modules\Ai\Application\AiCandidateApplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

function fixtureApplyNodeRun(): AiAnalysisRun
{
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'Fixture', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready',
    ]);

    return $content->analysisRuns()->create(['status' => 'pending']);
}

test('run() calls AiCandidateApplyService::apply() when test_mode is not set', function () {
    $run = fixtureApplyNodeRun();

    $service = Mockery::mock(AiCandidateApplyService::class);
    $service->shouldReceive('apply')->once()->with(Mockery::on(fn ($r) => $r->id === $run->id))->andReturn(['lexemes' => 1, 'grammar' => 0]);

    $result = (new ApplyNode($service))->run(new GraphState(['ai_analysis_run_id' => $run->id]));

    expect($result->get('applied'))->toBe(['lexemes' => 1, 'grammar' => 0])
        ->and($result->has('skipped_reason'))->toBeFalse();
});

test('run() never calls AiCandidateApplyService::apply() when test_mode is true', function () {
    $run = fixtureApplyNodeRun();

    $service = Mockery::mock(AiCandidateApplyService::class);
    $service->shouldNotReceive('apply');

    $result = (new ApplyNode($service))->run(new GraphState(['ai_analysis_run_id' => $run->id, 'test_mode' => true]));

    expect($result->get('applied'))->toBeNull()
        ->and($result->get('skipped_reason'))->toBe('test_mode');
});
