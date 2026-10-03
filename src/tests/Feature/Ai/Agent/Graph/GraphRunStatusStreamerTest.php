<?php

use App\Modules\Ai\Domain\Models\AgentGraphRun;
use App\Modules\Ai\Application\Agent\Graph\GraphRunStatusStreamer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function fixtureGraphRun(string $status = AgentGraphRun::STATUS_RUNNING, ?string $currentNode = 'analyze'): AgentGraphRun
{
    return AgentGraphRun::query()->create([
        'graph_name' => 'ai_analysis',
        'current_node' => $currentNode,
        'status' => $status,
        'state' => [],
        'started_at' => now(),
    ]);
}

test('emits exactly one snapshot and stops immediately for a run already in a terminal state', function () {
    $run = fixtureGraphRun(AgentGraphRun::STATUS_COMPLETED, 'apply');

    $emitted = [];
    (new GraphRunStatusStreamer(maxIterations: 10, sleepMicroseconds: 0))
        ->stream($run->id, function (array $payload) use (&$emitted) {
            $emitted[] = $payload;
        });

    expect($emitted)->toBe([
        ['node_key' => 'apply', 'status' => 'completed'],
    ]);
});

test('emits not_found and stops when the run id does not exist', function () {
    $emitted = [];
    (new GraphRunStatusStreamer(maxIterations: 10, sleepMicroseconds: 0))
        ->stream(999999, function (array $payload) use (&$emitted) {
            $emitted[] = $payload;
        });

    expect($emitted)->toBe([
        ['node_key' => null, 'status' => 'not_found'],
    ]);
});

test('a still-running run exhausts its iteration budget, emitting the unchanged snapshot only once', function () {
    $run = fixtureGraphRun(AgentGraphRun::STATUS_RUNNING, 'match');

    $emitted = [];
    (new GraphRunStatusStreamer(maxIterations: 5, sleepMicroseconds: 0))
        ->stream($run->id, function (array $payload) use (&$emitted) {
            $emitted[] = $payload;
        });

    // Same snapshot every poll (nothing external changed it) — deduped to
    // one emission, not five, even though the budget allowed five polls.
    expect($emitted)->toBe([
        ['node_key' => 'match', 'status' => 'running'],
    ]);
});

test('a status change between polls is emitted as a second event', function () {
    $run = fixtureGraphRun(AgentGraphRun::STATUS_RUNNING, 'analyze');

    $emitted = [];
    $iteration = 0;
    (new GraphRunStatusStreamer(maxIterations: 4, sleepMicroseconds: 0))
        ->stream($run->id, function (array $payload) use (&$emitted, &$iteration, $run) {
            $emitted[] = $payload;
            $iteration++;

            // Simulate GraphRunner advancing to the next step after the
            // first poll observes "analyze" — same DB row a real queue
            // worker would update mid-stream.
            if ($iteration === 1) {
                $run->update(['current_node' => 'match', 'status' => AgentGraphRun::STATUS_COMPLETED]);
            }
        });

    expect($emitted)->toBe([
        ['node_key' => 'analyze', 'status' => 'running'],
        ['node_key' => 'match', 'status' => 'completed'],
    ]);
});
