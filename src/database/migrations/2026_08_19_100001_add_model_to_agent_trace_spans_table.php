<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which model actually served this `llm_call` span — needed so
 * DatabaseSpanRecorder::calculateCostUsd() can look up `model_pricing` by
 * model instead of assuming the single hardcoded gpt-4o-mini every span
 * before this migration implicitly used. Nullable: old spans and any
 * span_type other than llm_call never had a model to record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_trace_spans', function (Blueprint $table): void {
            $table->string('model')->nullable()->after('completion_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('agent_trace_spans', function (Blueprint $table): void {
            $table->dropColumn('model');
        });
    }
};
