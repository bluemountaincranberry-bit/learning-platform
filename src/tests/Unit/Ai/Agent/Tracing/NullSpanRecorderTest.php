<?php

use App\Modules\Ai\Application\Agent\Tracing\NullSpanRecorder;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;

test('startSpan does not throw and returns a usable span id', function () {
    $recorder = new NullSpanRecorder;
    $trace = TraceContext::newTrace();

    $spanId = $recorder->startSpan($trace, 'agent_turn', 'agent_loop.run');

    expect($spanId)->toBeString()->not->toBe('');
});

test('endSpan does not throw for any status', function () {
    $recorder = new NullSpanRecorder;
    $trace = TraceContext::newTrace();
    $spanId = $recorder->startSpan($trace, 'llm_call', 'agent_loop.chat');

    $recorder->endSpan($spanId, 'ok', ['prompt_tokens' => 10, 'completion_tokens' => 5]);
    $recorder->endSpan($spanId, 'error', ['error' => 'boom']);
})->throwsNoExceptions();

test('a child TraceContext built from a NullSpanRecorder span id still carries the same trace id', function () {
    $recorder = new NullSpanRecorder;
    $trace = TraceContext::newTrace();

    $spanId = $recorder->startSpan($trace, 'agent_turn', 'agent_loop.run');
    $child = $trace->withParentSpan($spanId);

    expect($child->traceId)->toBe($trace->traceId)
        ->and($child->parentSpanId)->toBe($spanId);
});
