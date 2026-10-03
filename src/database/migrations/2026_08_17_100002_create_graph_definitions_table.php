<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Same draft/publish shape as prompt_templates: `key` matches the
 * `graph_name` any `GraphDefinitionResolver::resolve()` caller already
 * passes (agent_graph_runs.graph_name, config('ai.graph.definitions')
 * keys) — `active_version_id` null means "no override, resolver falls
 * back to the hand-built PHP GraphDefinition class" exactly like an
 * unpublished prompt falls back to its caller's hardcoded default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graph_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('active_version_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graph_definitions');
    }
};
