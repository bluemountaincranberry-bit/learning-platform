<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lesson-scoped analog of `content_grammar_candidates` — see the sibling
 * lesson_lexeme_candidates migration's docblock for why this is a separate
 * table rather than a shared/nullable-FK one.
 *
 * Unlike lexeme matches (deferred — see LessonCandidateMatchingService's
 * docblock for the UserLexemeProgress/SrsCard schema constraint), a
 * `matched_grammar_rule_id` hit is auto-linked into the user's own
 * `UserGrammarRule` via GrammarProgressService::startLearning() — that
 * table has no content-occurrence coupling, so this is a safe, simple
 * insert, not a publish into the shared catalog.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_grammar_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_analysis_run_id')->constrained('lesson_analysis_runs')->cascadeOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->text('body')->nullable();
            $table->text('example')->nullable();
            $table->text('example_translation')->nullable();
            $table->text('note')->nullable();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->foreignId('matched_grammar_rule_id')->nullable()->constrained('grammar_rules')->nullOnDelete();
            $table->decimal('match_score', 4, 3)->nullable();
            $table->string('status', 16)->default('pending'); // pending | linked | new

            $table->timestamps();

            $table->index(['lesson_analysis_run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_grammar_candidates');
    }
};
