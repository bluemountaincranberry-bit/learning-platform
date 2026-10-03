<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only attempt log for the "Ready to watch" exam — same shape as
     * srs_reviews (log every attempt, derive current status by querying the
     * log) rather than a single mutable readiness flag, so retakes and
     * history come for free and nothing needs invalidating when a learner
     * un-marks a word/rule as learned later.
     */
    public function up(): void
    {
        Schema::create('content_exam_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_id')->constrained('contents')->cascadeOnDelete();
            $table->unsignedInteger('total_cards');
            $table->unsignedInteger('correct_count');
            $table->decimal('score_pct', 5, 2);
            $table->boolean('passed');
            // Snapshot of the threshold in effect at attempt time, so a past
            // attempt stays interpretable if config('ai.exam.pass_threshold_pct')
            // changes later.
            $table->decimal('pass_threshold_pct', 5, 2);
            // Per-card detail (prompt/answer/correct/model_answer) for the
            // review screen — JSON, not a child table, same tradeoff already
            // accepted for content_lexeme_candidates.examples: simplicity now,
            // normalize only if cross-exam per-item analytics becomes a real need.
            $table->json('items');
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->index(['user_id', 'content_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_exam_attempts');
    }
};
