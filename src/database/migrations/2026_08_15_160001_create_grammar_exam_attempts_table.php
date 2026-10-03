<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only attempt log for the grammar warm-up (pre/post-exam), same
     * "log every attempt, derive state from the log" shape as
     * content_exam_attempts — but one row per (user, grammar_rule) per
     * session rather than one row for the whole session, because
     * GrammarConfidenceService needs a per-rule score to recompute
     * confidence_calculated per topic, not just an aggregate.
     */
    public function up(): void
    {
        Schema::create('grammar_exam_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grammar_rule_id')->constrained('grammar_rules')->cascadeOnDelete();
            // Nullable: a rule can in principle be warmed up outside any
            // specific content in the future, even though today every
            // ContentGrammarPreExamController call comes from a content page.
            $table->foreignId('content_id')->nullable()->constrained('contents')->nullOnDelete();
            $table->string('type');
            $table->unsignedInteger('total_cards');
            $table->unsignedInteger('correct_count');
            $table->decimal('score_pct', 5, 2);
            $table->json('items');
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->index(['user_id', 'grammar_rule_id', 'completed_at']);
            $table->index(['user_id', 'content_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grammar_exam_attempts');
    }
};
