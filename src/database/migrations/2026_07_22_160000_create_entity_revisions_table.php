<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generic, reusable revision log — not specific to grammar rules.
     * `revisionable_*` is polymorphic so any model can adopt `HasRevisions`.
     * `causer_*` is polymorphic too (nullable) since a revision can come from
     * an admin (User) or from an unattended AI action with no user causer.
     */
    public function up(): void
    {
        Schema::create('entity_revisions', function (Blueprint $table): void {
            $table->id();
            $table->morphs('revisionable');
            $table->nullableMorphs('causer');
            $table->string('source', 16)->default('admin'); // admin | ai
            $table->json('changes'); // {field: {old, new}}
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['revisionable_type', 'revisionable_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_revisions');
    }
};
