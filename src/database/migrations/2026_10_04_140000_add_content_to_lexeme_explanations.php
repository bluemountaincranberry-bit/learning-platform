<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Explanations are per-context: the same word met in another content
     * gets its own stored explanation, and the word page lists every
     * variant with a link to the content it came from. Rows written before
     * this migration keep content_id NULL and show as the generic variant.
     */
    public function up(): void
    {
        Schema::table('lexeme_explanations', function (Blueprint $table): void {
            $table->dropUnique(['lexeme_id', 'language']);
            $table->foreignId('content_id')->nullable()->constrained('contents')->nullOnDelete();
            $table->unique(['lexeme_id', 'language', 'content_id']);
        });
    }

    public function down(): void
    {
        Schema::table('lexeme_explanations', function (Blueprint $table): void {
            $table->dropUnique(['lexeme_id', 'language', 'content_id']);
            $table->dropConstrainedForeignId('content_id');
            $table->unique(['lexeme_id', 'language']);
        });
    }
};
