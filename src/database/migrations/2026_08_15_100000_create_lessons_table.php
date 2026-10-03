<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Мои занятия" — a private, per-user record of a tutor lesson. Deliberately
 * NOT modeled as a `Content` row: `Content` is public/curated catalog
 * material reviewed by admins, this is a student's own private notes and
 * must never surface in the admin Content review UI. See
 * docs/architecture/ai-platform-vision.md and the human-in-the-loop rule
 * for the published Lexeme/GrammarRule catalog — candidates extracted from
 * a Lesson stay scoped to lesson_lexeme_candidates/lesson_grammar_candidates
 * (separate migration), never content_lexeme_candidates/content_grammar_candidates.
 *
 * `status` mirrors `AgentConversation::STATUS_ACTIVE`/`STATUS_ARCHIVED` on
 * purpose — a Lesson is reopenable/appendable at any time, not a one-shot
 * draft->ready pipeline like Content, so it needs no richer status machine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('tutor')->nullable();
            // ISO 639-1 of the language being learned — same convention/
            // fallback as Content::language, used for exact/embedding lexeme
            // matching in LessonCandidateMatchingService.
            $table->string('language', 8)->default('en');
            $table->string('status', 16)->default('active'); // active | archived
            // Accumulated transcript: chat message text + any extracted PDF
            // text, appended as the lesson is built up. Read as-is by
            // LessonAnalysisService on each "Разобрать урок" click — see
            // that service's docblock for why re-analyzing the whole thing
            // each time (relying on embedding dedup) was chosen over
            // incremental diffing.
            $table->text('source_text')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
