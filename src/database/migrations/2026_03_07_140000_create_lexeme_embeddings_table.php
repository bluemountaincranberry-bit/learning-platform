<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vectors stored as JSON array of floats for portability.
     * pgvector can be added later (separate migration) for similarity index if needed.
     */
    public function up(): void
    {
        Schema::create('lexeme_embeddings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_lexeme_id')->constrained('content_lexemes')->cascadeOnDelete();
            $table->json('embedding'); // array of floats
            $table->string('model_version', 64);
            $table->timestamps();

            $table->unique(['content_lexeme_id', 'model_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lexeme_embeddings');
    }
};
