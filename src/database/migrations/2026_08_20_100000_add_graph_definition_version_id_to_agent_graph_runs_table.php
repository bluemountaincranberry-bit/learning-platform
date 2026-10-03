<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which published `graph_definition_versions` row (if any) this run
 * actually executed with — see `ResolvedGraphDefinition`/
 * `GraphDefinitionResolver`'s docblocks. Nullable and `nullOnDelete`:
 * null means "ran from the hand-built PHP class", and a run's own record
 * of what happened must survive that version later being edited away
 * (a new draft/publish never deletes an old version row, but this stays
 * defensive regardless).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_graph_runs', function (Blueprint $table): void {
            $table->foreignId('graph_definition_version_id')->nullable()->after('graph_name')
                ->constrained('graph_definition_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agent_graph_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('graph_definition_version_id');
        });
    }
};
