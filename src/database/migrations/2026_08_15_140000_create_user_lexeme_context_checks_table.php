<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Was the last self-check on this word good or bad", per (user,
     * canonical lexeme) — one row, upserted on every SelfCheckService
     * submit (quick-check/cloze/listening and context-practice translation
     * both go through the same submit path). Deliberately last-result-only,
     * not a per-attempt event log: SrsCard/IntervalCalculator already own
     * real spaced-repetition scheduling, this table only feeds a simple
     * "prioritize retrying Needs-work words" reorder in context practice.
     * Same shape as user_lexeme_skips: a small dedicated table rather than
     * overloading user_lexeme_progress (whose learned_at is NOT NULL and
     * whose row only exists once a word is actually marked learned — a
     * context-practice word may not be).
     */
    public function up(): void
    {
        Schema::create('user_lexeme_context_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lexeme_id')->constrained('lexemes')->cascadeOnDelete();
            $table->string('last_result'); // 'correct' | 'needs_work'
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->unique(['user_id', 'lexeme_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_lexeme_context_checks');
    }
};
