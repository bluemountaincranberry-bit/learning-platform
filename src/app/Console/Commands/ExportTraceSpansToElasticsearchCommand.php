<?php

namespace App\Console\Commands;

use App\Modules\Ai\Infrastructure\ElasticSpanExporter;
use Illuminate\Console\Command;

/**
 * Task 5.3: entry point for `ElasticSpanExporter` — same shape as
 * `IndexRagCorpusCommand` for the RAG corpus (task 2.3), except this ships
 * already-recorded `agent_trace_spans` rows instead of computing anything
 * new. Meant to run on a schedule (e.g. every few minutes) once Tier 2
 * tracing is actually turned on, not on every request.
 */
class ExportTraceSpansToElasticsearchCommand extends Command
{
    protected $signature = 'ai:export-trace-spans {--limit=500 : Maximum number of spans to export in this run}';

    protected $description = 'Export closed agent_trace_spans rows to Elasticsearch (Tier 2 tracing, task 5.3).';

    public function handle(ElasticSpanExporter $exporter): int
    {
        if (! $exporter->enabled()) {
            $this->warn('ELASTICSEARCH_ENABLED is false (see config/elasticsearch.php) — nothing to do.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $count = $exporter->exportPending($limit);

        $this->info("Exported {$count} span(s) to Elasticsearch.");

        return self::SUCCESS;
    }
}
