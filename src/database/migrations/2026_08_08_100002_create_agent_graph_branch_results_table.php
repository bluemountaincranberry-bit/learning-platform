<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 4.11 — one row per branch of a `ParallelNode` fan-out
 * (`Bus::batch()`, docs/architecture/agent-framework-roadmap.md, section
 * 12): each `GraphBranchJob` writes its own result here independently;
 * `ParallelNode`'s fan-in reads all rows for `graph_run_id` back into
 * `GraphState` once every branch has completed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_graph_branch_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('graph_run_id')->constrained('agent_graph_runs')->cascadeOnDelete();
            $table->string('branch_key', 64);
            $table->json('result')->nullable();
            $table->string('status', 16); // running | completed | failed
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['graph_run_id', 'branch_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_graph_branch_results');
    }
};
