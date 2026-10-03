<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_lexeme_candidates', function (Blueprint $table): void {
            // Task 10.1: separates the base/dictionary form ("run") from the
            // occurrence's own displayed text ("ran", already on `text`).
            // Matching/canonical-lexeme creation key off `normalized_lemma`
            // instead of `normalized_text` so inflected forms of the same
            // word collapse onto one Lexeme instead of creating a duplicate.
            $table->string('lemma')->nullable()->after('text');
            $table->string('normalized_lemma')->nullable()->index()->after('normalized_text');
            // Grammar tags for this specific occurrence (e.g. {"tense":
            // "past"} for "ran") — a property of the occurrence, not of the
            // lemma itself, so it lives here rather than on the Lexeme.
            $table->json('grammar_features')->nullable()->after('level');
        });
    }

    public function down(): void
    {
        Schema::table('content_lexeme_candidates', function (Blueprint $table): void {
            $table->dropColumn(['lemma', 'normalized_lemma', 'grammar_features']);
        });
    }
};
