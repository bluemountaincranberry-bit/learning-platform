<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_trace_spans', function (Blueprint $table): void {
            $table->id();
            $table->string('trace_id', 36);
            $table->string('span_id', 36)->unique();
            $table->string('parent_span_id', 36)->nullable();
            $table->string('span_type', 32); // llm_call | tool_call | agent_turn | handoff
            $table->string('name');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status', 16)->nullable(); // ok | error, null while a span is still open
            $table->timestamps();

            $table->index(['trace_id', 'started_at']);
            $table->index('parent_span_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_trace_spans');
    }
};
