<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 5.2: tokens were already recorded per llm_call span (task 1.7) but
 * cost in USD had to be recomputed on demand from a fixed price
 * (ai:agent-trace-report --weekly). Storing it on the span itself means
 * cost is fixed at the price in effect when the call happened — a later
 * price change in config('ai.pricing') does not retroactively change what
 * historical spans "cost" (see DatabaseSpanRecorder::calculateCostUsd()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_trace_spans', function (Blueprint $table): void {
            $table->decimal('cost_usd', 10, 6)->nullable()->after('completion_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('agent_trace_spans', function (Blueprint $table): void {
            $table->dropColumn('cost_usd');
        });
    }
};
