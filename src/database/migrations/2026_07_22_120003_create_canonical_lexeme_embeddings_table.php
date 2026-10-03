<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separate from `lexeme_embeddings`, which is keyed to a content occurrence
     * (`content_lexeme_id`), not the canonical lexeme. Candidate matching needs
     * embeddings keyed to the canonical `lexeme_id` directly.
     *
     * Vectors stored as JSON array of floats, same portability tradeoff already
     * made for `lexeme_embeddings`.
     */
    public function up(): void
    {
        Schema::create('canonical_lexeme_embeddings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lexeme_id')->constrained('lexemes')->cascadeOnDelete();
            $table->json('embedding');
            $table->string('model_version', 64);
            $table->timestamps();

            $table->unique(['lexeme_id', 'model_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canonical_lexeme_embeddings');
    }
};
