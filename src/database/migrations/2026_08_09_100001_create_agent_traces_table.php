<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 5.2 / ai-platform-vision.md section 10: a denormalized summary row
 * per trace, written when the root agent_turn span (parent_span_id null)
 * closes — `agent_trace_spans` stays the single source of truth for the
 * detailed span tree, this table only exists so a "recent traces" or
 * "cost by day" dashboard/list doesn't have to re-aggregate every span on
 * every read. See DatabaseSpanRecorder::upsertTraceSummary().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_traces', function (Blueprint $table): void {
            $table->id();
            $table->string('trace_id', 36)->unique();
            $table->string('entry_agent_type', 64)->nullable();
            $table->timestamp('started_at');
            $table->unsignedInteger('total_duration_ms')->nullable();
            $table->decimal('total_cost_usd', 10, 6)->nullable();
            $table->string('status', 16)->nullable(); // ok | error
            $table->timestamps();

            $table->index('started_at');
            $table->index('entry_agent_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_traces');
    }
};
