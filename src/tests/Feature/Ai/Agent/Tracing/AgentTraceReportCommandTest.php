<?php

use App\Modules\Ai\Domain\Models\AgentTraceSpan;
use App\Modules\Ai\Application\Agent\Tracing\DatabaseSpanRecorder;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function recordSampleTrace(string $agentType, int $promptTokens, int $completionTokens): string
{
    $trace = TraceContext::newTrace();
    $recorder = new DatabaseSpanRecorder;

    $turnSpanId = $recorder->startSpan($trace, 'agent_turn', 'agent_loop.run', ['agent_type' => $agentType]);
    $childTrace = $trace->withParentSpan($turnSpanId);

    $llmSpanId = $recorder->startSpan($childTrace, 'llm_call', 'agent_loop.chat');
    $recorder->endSpan($llmSpanId, 'ok', ['prompt_tokens' => $promptTokens, 'completion_tokens' => $completionTokens]);

    $recorder->endSpan($turnSpanId, 'ok');

    return $trace->traceId;
}

test('--trace prints the span tree for that trace_id, indented by parent_span_id', function () {
    $traceId = recordSampleTrace('content_authoring', 10, 5);

    $exitCode = Artisan::call('ai:agent-trace-report', ['--trace' => $traceId]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('[agent_turn] agent_loop.run')
        ->and($output)->toContain('[llm_call] agent_loop.chat');
});

test('--trace reports failure for an unknown trace_id', function () {
    $exitCode = Artisan::call('ai:agent-trace-report', ['--trace' => 'does-not-exist']);

    expect($exitCode)->not->toBe(0);
});

test('--weekly summarizes tokens per agent_type over the last 7 days', function () {
    recordSampleTrace('content_authoring', 100, 40);
    recordSampleTrace('content_authoring', 50, 10);
    recordSampleTrace('student_tutor', 20, 5);

    // Outside the 7-day window — must not be counted.
    $oldTraceId = recordSampleTrace('content_authoring', 999, 999);
    AgentTraceSpan::query()->where('trace_id', $oldTraceId)->update(['started_at' => now()->subDays(30)]);

    $exitCode = Artisan::call('ai:agent-trace-report', ['--weekly' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('content_authoring')
        ->and($output)->toContain('student_tutor')
        ->and($output)->toContain('150') // combined prompt tokens for content_authoring within the window
        ->and($output)->toContain('50') // combined completion tokens for content_authoring within the window
        ->and($output)->not->toContain('999'); // the old, out-of-window trace must not be counted
});

test('--weekly reports success with a message when there is nothing to summarize', function () {
    $exitCode = Artisan::call('ai:agent-trace-report', ['--weekly' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('No agent turns recorded');
});

test('without --trace or --weekly, fails with a usage message', function () {
    $exitCode = Artisan::call('ai:agent-trace-report');
    $output = Artisan::output();

    expect($exitCode)->not->toBe(0)
        ->and($output)->toContain('Specify --trace');
});
