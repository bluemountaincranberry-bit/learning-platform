<?php

use App\Console\Commands\ExportTraceSpansToElasticsearchCommand;
use App\Modules\Ai\Domain\Models\AgentTraceSpan;
use App\Modules\Ai\Application\Agent\Tracing\DatabaseSpanRecorder;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;
use App\Modules\Ai\Infrastructure\ElasticSpanExporter;
use App\Modules\Ai\Infrastructure\ElasticsearchClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Task 5.3 acceptance test: run against the real Elasticsearch instance in
 * this environment (same convention as RagIndexingServiceTest — verified
 * reachable via a plain cluster health call, EPIC 2), not mocked. No
 * external network call is involved (unlike the RAG suite, which embeds via
 * OpenAI) — DatabaseSpanRecorder and ElasticSpanExporter are both pure
 * Postgres/Elasticsearch, so nothing here needs Http::fake().
 */
beforeEach(function () {
    $this->tracesIndex = 'agent_trace_spans_test_'.str_replace('.', '', uniqid('', true));
    config([
        'elasticsearch.enabled' => true,
        'elasticsearch.trace_spans.index' => $this->tracesIndex,
    ]);
});

afterEach(function () {
    app(ElasticsearchClient::class)->deleteIndex($this->tracesIndex);
});

function recordClosedSpan(string $spanType = 'llm_call', string $agentType = 'content_authoring'): AgentTraceSpan
{
    $trace = TraceContext::newTrace();
    $recorder = new DatabaseSpanRecorder;
    $spanId = $recorder->startSpan($trace, $spanType, 'agent_loop.chat', ['agent_type' => $agentType]);
    $recorder->endSpan($spanId, 'ok', ['prompt_tokens' => 10, 'completion_tokens' => 5]);

    return AgentTraceSpan::query()->where('span_id', $spanId)->firstOrFail();
}

test('exportPending ships closed spans to Elasticsearch and marks them exported', function () {
    $span = recordClosedSpan();

    $count = app(ElasticSpanExporter::class)->exportPending();

    expect($count)->toBe(1);
    $span->refresh();
    expect($span->exported_at)->not->toBeNull();

    $response = app(ElasticsearchClient::class)->search($this->tracesIndex, [
        'query' => ['term' => ['span_id' => $span->span_id]],
    ]);

    expect($response['hits']['hits'])->toHaveCount(1);
    $doc = $response['hits']['hits'][0]['_source'];
    expect($doc['trace_id'])->toBe($span->trace_id)
        ->and($doc['span_type'])->toBe('llm_call')
        ->and($doc['agent_type'])->toBe('content_authoring')
        ->and($doc['prompt_tokens'])->toBe(10)
        ->and($doc['completion_tokens'])->toBe(5)
        ->and($doc['cost_usd'])->not->toBeNull()
        ->and($doc['status'])->toBe('ok');
});

test('exportPending does not re-export an already-exported span', function () {
    recordClosedSpan();
    $exporter = app(ElasticSpanExporter::class);

    expect($exporter->exportPending())->toBe(1)
        ->and($exporter->exportPending())->toBe(0);
});

test('exportPending skips spans that have not closed yet', function () {
    $trace = TraceContext::newTrace();
    (new DatabaseSpanRecorder)->startSpan($trace, 'llm_call', 'agent_loop.chat');

    expect(app(ElasticSpanExporter::class)->exportPending())->toBe(0);
});

test('exportPending is a no-op when Elasticsearch is disabled', function () {
    config(['elasticsearch.enabled' => false]);
    recordClosedSpan();

    expect(app(ElasticSpanExporter::class)->exportPending())->toBe(0);
});

test('ExportTraceSpansToElasticsearchCommand exports pending spans end to end', function () {
    recordClosedSpan('llm_call', 'student_tutor');
    recordClosedSpan('tool_call', 'student_tutor');

    $this->artisan(ExportTraceSpansToElasticsearchCommand::class)
        ->expectsOutputToContain('Exported 2 span(s)')
        ->assertExitCode(0);

    $response = app(ElasticsearchClient::class)->search($this->tracesIndex, [
        'query' => ['match_all' => new stdClass],
    ]);
    expect($response['hits']['hits'])->toHaveCount(2);
});

test('ExportTraceSpansToElasticsearchCommand is a no-op when Elasticsearch is disabled', function () {
    config(['elasticsearch.enabled' => false]);

    $this->artisan(ExportTraceSpansToElasticsearchCommand::class)
        ->assertExitCode(0);
});
