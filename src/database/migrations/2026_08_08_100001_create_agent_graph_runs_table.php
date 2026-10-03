<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durable state for GraphRunner (task 4.1) — the Postgres source of truth
 * for a workflow run, distinct from any fast Redis projection (ADR-005): a
 * run paused at a HumanCheckpointNode (task 4.8) may sit here for hours or
 * days before an admin/user resumes it, so it must survive a Redis
 * eviction/restart. See docs/architecture/agent-framework-roadmap.md,
 * section 7 ("Хранение").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_graph_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('graph_name', 64);
            $table->json('state');
            $table->string('current_node', 64);
            $table->string('status', 16); // running | paused | completed | failed
            $table->text('failure_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['graph_name', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_graph_runs');
    }
};
