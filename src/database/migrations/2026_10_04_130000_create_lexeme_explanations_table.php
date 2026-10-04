<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Durable store for AI lexeme explanations, keyed by canonical lexeme
     * and content language — outlives the 7-day cache in
     * LexemeExplanationService and is shared across contents, so the word
     * page can show an explanation generated from any content.
     */
    public function up(): void
    {
        Schema::create('lexeme_explanations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lexeme_id')->constrained('lexemes')->cascadeOnDelete();
            $table->string('language', 8);
            $table->text('explanation');
            $table->timestamps();

            $table->unique(['lexeme_id', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lexeme_explanations');
    }
};
