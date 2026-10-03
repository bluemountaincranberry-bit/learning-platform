<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `nodes` is the same data shape `GraphNodeRegistry::buildDefinition()`
 * already accepts — `[{key, node, x, y}]`, where `node` is a node_key from
 * the closed `config('ai.graph.node_registry')` map (x/y are canvas
 * layout only, ignored by buildDefinition()). `edges` matches its
 * `edgeDefinitions` shape — `[{from, to, label}]`. Both are validated
 * against the live node registry only at *resolve* time
 * (GraphDefinitionResolver), not stored pre-validated here — the registry
 * itself can only change via a deploy, so re-checking on every resolve is
 * cheap and always current.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graph_definition_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('graph_definition_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('nodes');
            $table->json('edges');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['graph_definition_id', 'version']);
        });

        Schema::table('graph_definitions', function (Blueprint $table): void {
            $table->foreign('active_version_id')->references('id')->on('graph_definition_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('graph_definitions', function (Blueprint $table): void {
            $table->dropForeign(['active_version_id']);
        });

        Schema::dropIfExists('graph_definition_versions');
    }
};
