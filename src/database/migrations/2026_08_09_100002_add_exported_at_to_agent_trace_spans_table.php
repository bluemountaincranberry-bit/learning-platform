<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 5.3: marks a span as already shipped to the Elasticsearch Tier 2
 * export (config('elasticsearch.trace_spans')), so
 * ElasticSpanExporter::exportPending() only ever ships newly-closed spans
 * on each run instead of re-indexing the whole table every time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_trace_spans', function (Blueprint $table): void {
            $table->timestamp('exported_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('agent_trace_spans', function (Blueprint $table): void {
            $table->dropColumn('exported_at');
        });
    }
};
