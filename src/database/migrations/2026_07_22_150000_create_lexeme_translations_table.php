<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Word-level gloss, separate from `lexeme_examples.translation` (which is
     * the translation of one example sentence). A lexeme can have a gloss in
     * more than one target `language`, and/or a content-scoped one
     * (`content_id` set) alongside a globally curated one (`content_id` null)
     * — same pattern already used by `lexeme_examples.content_id`.
     */
    public function up(): void
    {
        Schema::create('lexeme_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lexeme_id')->constrained('lexemes')->cascadeOnDelete();
            $table->foreignId('content_id')->nullable()->constrained('contents')->nullOnDelete();
            $table->string('language', 8);
            $table->text('translation');
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['lexeme_id', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lexeme_translations');
    }
};
