<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lesson-scoped analog of `content_lexeme_candidates`, same column shape
 * (kept in sync with AiContentAnalysisService's schema so both services can
 * share the same JSON response parsing conventions). Deliberately a
 * separate table, not a shared one with `content_id`/`lesson_id` both
 * nullable — mixing a student's private lesson notes into the table the
 * admin Content review UI queries risks it leaking into that screen.
 *
 * `matched_lexeme_id` set = this text matched something already in the
 * shared canonical catalog (safe to surface as "already exists, open it").
 * Unmatched rows stay private to the lesson only — see the project's
 * human-in-the-loop rule for the published Lexeme catalog; nothing here
 * ever writes into `lexemes` automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_lexeme_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_analysis_run_id')->constrained('lesson_analysis_runs')->cascadeOnDelete();
            $table->string('text');
            $table->string('normalized_text')->index();
            $table->string('type', 16)->default('word'); // word | phrase | phrasal_verb | idiom | collocation
            $table->string('level', 4)->nullable();
            $table->unsignedInteger('frequency')->nullable();
            $table->text('translation')->nullable();
            $table->text('example')->nullable();
            $table->text('example_translation')->nullable();
            $table->json('examples')->nullable();
            $table->text('note')->nullable();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->foreignId('matched_lexeme_id')->nullable()->constrained('lexemes')->nullOnDelete();
            $table->decimal('match_score', 4, 3)->nullable();
            $table->string('status', 16)->default('pending'); // pending | matched | new

            $table->timestamps();

            $table->index(['lesson_analysis_run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_lexeme_candidates');
    }
};
