<?php

use App\Filament\Pages\AgentGraphRuns;
use App\Filament\Pages\AgentTraces;
use App\Filament\Pages\AgentTraceSpans;
use App\Modules\Ai\Domain\Models\AgentGraphRun;
use App\Modules\Ai\Domain\Models\AgentTrace;
use App\Modules\Ai\Domain\Models\AgentTraceSpan;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Task 6.8: read-only admin visibility into agent_traces/agent_trace_spans/
 * agent_graph_runs — proves each page renders and actually shows the rows
 * that exist, not just that the route resolves.
 */
uses(RefreshDatabase::class);

function actingAdminForObservability(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    test()->actingAs($admin, 'web');

    return $admin;
}

test('AgentTraces page renders and lists existing traces', function () {
    actingAdminForObservability();

    AgentTrace::query()->create([
        'trace_id' => 'trace-1234',
        'entry_agent_type' => 'student_tutor',
        'started_at' => now(),
        'total_duration_ms' => 4200,
        'total_cost_usd' => 0.0123,
        'status' => 'ok',
    ]);

    Livewire::test(AgentTraces::class)
        ->assertOk()
        ->assertSee('student_tutor')
        ->assertSee('ok');
});

test('AgentTraces page renders an empty state with no traces', function () {
    actingAdminForObservability();

    Livewire::test(AgentTraces::class)
        ->assertOk()
        ->assertSee('No agent traces recorded yet.');
});

test('AgentTraceSpans page renders the span tree for one trace_id', function () {
    actingAdminForObservability();

    AgentTrace::query()->create([
        'trace_id' => 'trace-5678',
        'entry_agent_type' => 'student_tutor',
        'started_at' => now(),
        'status' => 'ok',
    ]);
    $rootSpanId = (string) Str::uuid();
    AgentTraceSpan::query()->create([
        'trace_id' => 'trace-5678',
        'span_id' => $rootSpanId,
        'parent_span_id' => null,
        'span_type' => 'agent_turn',
        'name' => 'agent_loop.run_streaming',
        'started_at' => now(),
        'ended_at' => now(),
        'duration_ms' => 500,
        'status' => 'ok',
    ]);
    AgentTraceSpan::query()->create([
        'trace_id' => 'trace-5678',
        'span_id' => (string) Str::uuid(),
        'parent_span_id' => $rootSpanId,
        'span_type' => 'tool_call',
        'name' => 'get_user_level',
        'started_at' => now(),
        'ended_at' => now(),
        'duration_ms' => 50,
        'status' => 'ok',
    ]);

    Livewire::test(AgentTraceSpans::class, ['trace_id' => 'trace-5678'])
        ->assertOk()
        ->assertSee('agent_loop.run_streaming')
        ->assertSee('get_user_level');
});

test('AgentGraphRuns page renders and lists existing runs', function () {
    actingAdminForObservability();

    AgentGraphRun::query()->create([
        'graph_name' => 'study_plan',
        'state' => [],
        'current_node' => 'fan_out',
        'status' => 'running',
        'started_at' => now(),
    ]);

    Livewire::test(AgentGraphRuns::class)
        ->assertOk()
        ->assertSee('study_plan')
        ->assertSee('running');
});

test('a plain student account without any admin-panel role cannot access the observability pages', function () {
    $student = User::factory()->create();
    test()->actingAs($student, 'web');

    test()->get(AgentTraces::getUrl())->assertForbidden();
});
