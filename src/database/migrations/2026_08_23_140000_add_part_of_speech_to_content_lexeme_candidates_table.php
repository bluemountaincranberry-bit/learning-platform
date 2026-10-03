<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_lexeme_candidates', function (Blueprint $table): void {
            // Task 10.2: part of speech is now requested at extraction time
            // instead of only appearing later via the async
            // SuggestLexemeLevelJob (task 9.7) — that job remains a fallback
            // for lexemes created without one (tokenizer/manual path).
            $table->string('part_of_speech', 32)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('content_lexeme_candidates', function (Blueprint $table): void {
            $table->dropColumn('part_of_speech');
        });
    }
};
