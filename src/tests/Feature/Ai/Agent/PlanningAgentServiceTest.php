<?php

use App\Modules\Ai\Interfaces\Jobs\ResumeGraphJob;
use App\Modules\Ai\Domain\Models\AgentGraphRun;
use App\Modules\Ai\Application\Agent\Data\AgentChatResponse;
use App\Modules\Ai\Application\Agent\Graph\GraphDefinitionResolver;
use App\Modules\Ai\Application\Agent\Graph\GraphRunner;
use App\Modules\Ai\Application\Agent\Graph\GraphRunResult;
use App\Modules\Ai\Application\Agent\PlanningAgentService;
use App\Contracts\Ai\AiToolCallingClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Same sync-queue nesting behavior documented in ParallelNodeTest's file
 * docblock: start() dispatches the fan-out, which (QUEUE_CONNECTION=sync)
 * runs both specialist branches inline and pauses; a second, explicit call
 * (here, PlanningAgentService::resume(), the same entry point
 * ResumeGraphJob calls in production once Horizon genuinely delivers the
 * queued job) then finds every branch already completed and finishes the
 * run.
 */
uses(RefreshDatabase::class);

test('planning agent fans out to grammar and review specialists and merges their replies into one study plan', function () {
    $toolClient = Mockery::mock(AiToolCallingClient::class);
    $toolClient->shouldReceive('chat')->twice()->andReturnUsing(function ($messages) {
        // Both branches receive the same task text ("...IELTS...") — the
        // system prompt (set per specialist in
        // GrammarAgentService/ReviewAgentService::systemPromptText()) is
        // what actually distinguishes which branch is asking.
        $systemMessage = collect($messages)->firstWhere('role', 'system')['content'] ?? '';

        return str_contains($systemMessage, 'grammar specialist')
            ? new AgentChatResponse('Focus on conditional sentences and passive voice this week.')
            : new AgentChatResponse('Review your 10 weakest words over the next 3 days.');
    });
    app()->instance(AiToolCallingClient::class, $toolClient);

    $service = app(PlanningAgentService::class);
    $paused = $service->start(1, 'Prepare me for IELTS in 3 months');

    expect($paused->status)->toBe(GraphRunResult::STATUS_PAUSED);

    $dbRun = AgentGraphRun::query()->where('graph_name', 'study_plan')->firstOrFail();
    expect($dbRun->status)->toBe(AgentGraphRun::STATUS_PAUSED);

    $completed = $service->resume($dbRun->id);

    expect($completed->status)->toBe(GraphRunResult::STATUS_COMPLETED)
        ->and($completed->state->get('final_reply'))->toContain('Focus on conditional sentences and passive voice this week.')
        ->and($completed->state->get('final_reply'))->toContain('Review your 10 weakest words over the next 3 days.');

    $dbRun->refresh();
    expect($dbRun->status)->toBe(AgentGraphRun::STATUS_COMPLETED);
});

test('planning agent graph resumes correctly through the real ResumeGraphJob/GraphDefinitionResolver path', function () {
    $toolClient = Mockery::mock(AiToolCallingClient::class);
    $toolClient->shouldReceive('chat')->twice()->andReturn(new AgentChatResponse('ok'));
    app()->instance(AiToolCallingClient::class, $toolClient);

    $service = app(PlanningAgentService::class);
    $service->start(1, 'plan my study');

    $dbRun = AgentGraphRun::query()->where('graph_name', 'study_plan')->firstOrFail();
    expect($dbRun->status)->toBe(AgentGraphRun::STATUS_PAUSED);

    // GraphDefinitionResolver + config('ai.graph.definitions') resolving
    // StudyPlanGraph::class for real (task 4.11/4.12 wiring), not a test double.
    (new ResumeGraphJob($dbRun->id))->handle(app(GraphRunner::class), app(GraphDefinitionResolver::class));

    $dbRun->refresh();
    expect($dbRun->status)->toBe(AgentGraphRun::STATUS_COMPLETED)
        ->and($dbRun->state['final_reply'])->toContain('ok');
});
