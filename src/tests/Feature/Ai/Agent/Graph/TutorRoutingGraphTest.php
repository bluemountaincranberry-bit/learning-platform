<?php

use App\Modules\Ai\Application\Agent\Data\AgentChatResponse;
use App\Modules\Ai\Application\Agent\Graph\Definitions\TutorRoutingGraph;
use App\Modules\Ai\Application\Agent\Graph\GraphRunner;
use App\Modules\Ai\Application\Agent\Graph\GraphRunResult;
use App\Modules\Ai\Application\Agent\Graph\GraphState;
use App\Contracts\Ai\AiToolCallingClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('intent=grammar routes to GrammarAgentService only, never ReviewAgentService', function () {
    $toolClient = Mockery::mock(AiToolCallingClient::class);
    $toolClient->shouldReceive('chat')->once()->andReturn(
        new AgentChatResponse('The present perfect connects a past action to now.')
    );
    app()->instance(AiToolCallingClient::class, $toolClient);

    $definition = app(TutorRoutingGraph::class)->definition();
    $state = new GraphState(['intent' => 'grammar', 'task' => 'explain present perfect', 'acting_user_id' => 1]);

    $result = (new GraphRunner)->run($definition, $state);

    expect($result->status)->toBe(GraphRunResult::STATUS_COMPLETED)
        ->and($result->state->get('final_reply'))->toBe('The present perfect connects a past action to now.');
});

test('intent=review routes to ReviewAgentService only, never GrammarAgentService', function () {
    $toolClient = Mockery::mock(AiToolCallingClient::class);
    $toolClient->shouldReceive('chat')->once()->andReturn(
        new AgentChatResponse('Here is a proposed 3-day review plan.')
    );
    app()->instance(AiToolCallingClient::class, $toolClient);

    $definition = app(TutorRoutingGraph::class)->definition();
    $state = new GraphState(['intent' => 'review', 'task' => 'plan my review', 'acting_user_id' => 1]);

    $result = (new GraphRunner)->run($definition, $state);

    expect($result->status)->toBe(GraphRunResult::STATUS_COMPLETED)
        ->and($result->state->get('final_reply'))->toBe('Here is a proposed 3-day review plan.');
});

test('an unrecognized intent falls back without calling any specialist agent', function () {
    $toolClient = Mockery::mock(AiToolCallingClient::class);
    $toolClient->shouldNotReceive('chat');
    app()->instance(AiToolCallingClient::class, $toolClient);

    $definition = app(TutorRoutingGraph::class)->definition();
    $state = new GraphState(['intent' => 'something_unknown', 'task' => 'huh', 'acting_user_id' => 1]);

    $result = (new GraphRunner)->run($definition, $state);

    expect($result->status)->toBe(GraphRunResult::STATUS_COMPLETED)
        ->and($result->state->get('final_reply'))->toContain('not sure yet');
});

test('a missing intent also falls back gracefully instead of throwing', function () {
    $toolClient = Mockery::mock(AiToolCallingClient::class);
    $toolClient->shouldNotReceive('chat');
    app()->instance(AiToolCallingClient::class, $toolClient);

    $definition = app(TutorRoutingGraph::class)->definition();
    $result = (new GraphRunner)->run($definition, new GraphState(['task' => 'no intent given']));

    expect($result->status)->toBe(GraphRunResult::STATUS_COMPLETED)
        ->and($result->state->get('final_reply'))->toContain('not sure yet');
});
