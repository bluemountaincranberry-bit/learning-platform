<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_lexemes', function (Blueprint $table): void {
            // Task 10.1: grammar tags for this specific occurrence (e.g.
            // {"tense": "past"} for "ran"), copied over from the AI
            // candidate that created this row. Null for tokenizer/manual
            // occurrences and for occurrences where `text` already equals
            // the lemma (no inflection to describe).
            $table->json('grammar_features')->nullable()->after('lexeme_id');
        });
    }

    public function down(): void
    {
        Schema::table('content_lexemes', function (Blueprint $table): void {
            $table->dropColumn('grammar_features');
        });
    }
};
