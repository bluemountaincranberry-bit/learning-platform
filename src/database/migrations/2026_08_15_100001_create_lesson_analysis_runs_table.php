<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lesson-scoped analog of `ai_analysis_runs`. Kept as its own table rather
 * than making `ai_analysis_runs.content_id` nullable + adding a
 * `lesson_id` — that table's model/services assume `$run->content` is
 * always present (AiContentAnalysisService, CandidateMatchingService); a
 * parallel, simpler table avoids nullable-FK branching throughout code that
 * has no reason to know about Lessons. No `config`/`coverage_pct`/
 * `retried_for_coverage` columns — LessonAnalysisService intentionally
 * skips the transcript-chunking/coverage-retry machinery built for long
 * video transcripts (lesson notes are short).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_analysis_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->string('status', 32)->default('pending'); // pending | running | completed | failed
            $table->string('provider', 32)->nullable();
            $table->string('model', 64)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['lesson_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_analysis_runs');
    }
};
