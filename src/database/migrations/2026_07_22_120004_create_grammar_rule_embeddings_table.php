<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors `lexeme_embeddings`' JSON-storage convention; grammar rules have
     * no separate occurrence table, so this keys directly to the canonical
     * `grammar_rule_id`.
     */
    public function up(): void
    {
        Schema::create('grammar_rule_embeddings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grammar_rule_id')->constrained('grammar_rules')->cascadeOnDelete();
            $table->json('embedding');
            $table->string('model_version', 64);
            $table->timestamps();

            $table->unique(['grammar_rule_id', 'model_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grammar_rule_embeddings');
    }
};
