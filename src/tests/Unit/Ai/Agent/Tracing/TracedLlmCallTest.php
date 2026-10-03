<?php

use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\Agent\Tracing\SpanRecorder;
use App\Modules\Ai\Application\Agent\Tracing\TracedLlmCall;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use App\Modules\Ai\Application\Data\TokenUsage;
use App\Contracts\Ai\AiClientInterface;
use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\AiUsageReportingClient;

uses(Tests\TestCase::class);

test('completeJson() starts a span, forwards the call, and ends it ok with usage/model from the client', function () {
    $recorder = Mockery::mock(SpanRecorder::class);
    $recorder->shouldReceive('startSpan')
        ->once()
        ->with(Mockery::type(TraceContext::class), 'llm_call', 'my_feature.completeJson', ['feature' => 'my_feature'])
        ->andReturn('span-1');
    $recorder->shouldReceive('endSpan')
        ->once()
        ->with('span-1', 'ok', ['prompt_tokens' => 10, 'completion_tokens' => 5, 'model' => 'gpt-4o-mini']);

    $client = Mockery::mock(AiJsonClient::class, AiUsageReportingClient::class);
    $client->shouldReceive('completeJson')->once()->with('sys', 'usr', ['a' => 'b'], 'gpt-4o-mini')->andReturn(['ok' => true]);
    $client->shouldReceive('lastUsage')->once()->andReturn(new TokenUsage(10, 5));
    $client->shouldReceive('lastModel')->once()->andReturn('gpt-4o-mini');

    $result = (new TracedLlmCall($recorder))->completeJson(
        $client,
        TraceContext::newTrace(),
        'my_feature.completeJson',
        ['feature' => 'my_feature'],
        'sys',
        'usr',
        ['a' => 'b'],
        'gpt-4o-mini',
    );

    expect($result)->toBe(['ok' => true]);
});

test('completeJson() ends the span as error and rethrows when the client throws', function () {
    $recorder = Mockery::mock(SpanRecorder::class);
    $recorder->shouldReceive('startSpan')->once()->andReturn('span-1');
    $recorder->shouldReceive('endSpan')->once()->with('span-1', 'error', ['error' => 'boom']);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andThrow(new AiClientException('boom'));

    expect(fn () => (new TracedLlmCall($recorder))->completeJson($client, TraceContext::newTrace(), 'x', [], 'sys', 'usr'))
        ->toThrow(AiClientException::class, 'boom');
});

test('completeJson() records null usage/model when the client does not implement AiUsageReportingClient', function () {
    $recorder = Mockery::mock(SpanRecorder::class);
    $recorder->shouldReceive('startSpan')->once()->andReturn('span-1');
    $recorder->shouldReceive('endSpan')->once()->with('span-1', 'ok', ['prompt_tokens' => null, 'completion_tokens' => null, 'model' => null]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn(['ok' => true]);

    (new TracedLlmCall($recorder))->completeJson($client, TraceContext::newTrace(), 'x', [], 'sys', 'usr');
});

test('complete() starts a span, forwards the call, and ends it ok', function () {
    $recorder = Mockery::mock(SpanRecorder::class);
    $recorder->shouldReceive('startSpan')->once()->andReturn('span-1');
    $recorder->shouldReceive('endSpan')->once()->with('span-1', 'ok', ['prompt_tokens' => 3, 'completion_tokens' => 2, 'model' => 'gpt-4o']);

    $client = Mockery::mock(AiClientInterface::class, AiUsageReportingClient::class);
    $client->shouldReceive('complete')->once()->with('sys', 'usr', 'gpt-4o')->andReturn('the answer');
    $client->shouldReceive('lastUsage')->once()->andReturn(new TokenUsage(3, 2));
    $client->shouldReceive('lastModel')->once()->andReturn('gpt-4o');

    $result = (new TracedLlmCall($recorder))->complete($client, TraceContext::newTrace(), 'x', [], 'sys', 'usr', 'gpt-4o');

    expect($result)->toBe('the answer');
});

test('complete() ends the span as error and rethrows when the client throws', function () {
    $recorder = Mockery::mock(SpanRecorder::class);
    $recorder->shouldReceive('startSpan')->once()->andReturn('span-1');
    $recorder->shouldReceive('endSpan')->once()->with('span-1', 'error', ['error' => 'nope']);

    $client = Mockery::mock(AiClientInterface::class);
    $client->shouldReceive('complete')->once()->andThrow(new AiClientException('nope'));

    expect(fn () => (new TracedLlmCall($recorder))->complete($client, TraceContext::newTrace(), 'x', [], 'sys', 'usr'))
        ->toThrow(AiClientException::class, 'nope');
});
