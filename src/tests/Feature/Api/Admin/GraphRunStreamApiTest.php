<?php

use App\Modules\Ai\Domain\Models\AgentGraphRun;
use App\Modules\User\Models\User;
use App\Modules\Ai\Application\Agent\Graph\GraphRunStatusStreamer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Bind a fast (no real sleep, tiny budget), same shape as
 * TutorConversationControllerTest's SSE parsing convention (parseSseEvents
 * defined there — redefined here too since Pest test files don't share
 * plain function scope across files).
 */
beforeEach(function () {
    app()->bind(GraphRunStatusStreamer::class, fn () => new GraphRunStatusStreamer(maxIterations: 2, sleepMicroseconds: 0));
});

function parseGraphRunSseEvents(string $streamedContent): array
{
    $events = [];
    foreach (explode("\n\n", trim($streamedContent)) as $rawEvent) {
        foreach (explode("\n", $rawEvent) as $line) {
            if (str_starts_with($line, 'data:')) {
                $events[] = json_decode(trim(substr($line, 5)), true);
            }
        }
    }

    return $events;
}

test('stream() returns SSE events reflecting the run current_node/status', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $run = AgentGraphRun::query()->create([
        'graph_name' => 'ai_analysis',
        'current_node' => 'apply',
        'status' => AgentGraphRun::STATUS_COMPLETED,
        'state' => [],
        'started_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get("/api/admin/ai-builder/graph-runs/{$run->id}/stream")->assertOk();

    expect(parseGraphRunSseEvents($response->streamedContent()))->toBe([
        ['node_key' => 'apply', 'status' => 'completed'],
    ]);
});

test('a non-admin gets 403 from the graph-run stream endpoint', function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('user');

    $run = AgentGraphRun::query()->create([
        'graph_name' => 'ai_analysis', 'current_node' => 'apply', 'status' => AgentGraphRun::STATUS_COMPLETED, 'state' => [], 'started_at' => now(),
    ]);

    $this->actingAs($user)->get("/api/admin/ai-builder/graph-runs/{$run->id}/stream")->assertForbidden();
});
