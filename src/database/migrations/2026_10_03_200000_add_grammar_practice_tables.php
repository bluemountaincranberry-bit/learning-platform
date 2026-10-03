<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VIK-31 grammar practice (spec: docs/product/grammar-exercises-block.md).
 *
 * - grammar_rule_exercises gets the fields the five exercise types need
 *   (instruction line, hint, accepted answers, build tiles), where the
 *   exercise came from (origin admin|ai) and a dedup key (normalized prompt,
 *   or normalized answer for build, whose prompts are generic) used to drop
 *   duplicates at generation time.
 * - grammar_exercise_reports: a learner's "Report a bad exercise"; hides the
 *   exercise for that learner at once, and for everyone at three reports.
 * - grammar_exercise_generations: one row per queued AI batch. Gives the
 *   round screen a status to poll and the rate limit (one running batch per
 *   rule, a few batches per rule per learner per day) something to count.
 * - grammar_exam_attempts.level: Easy/Medium/Hard of a practice round.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grammar_rule_exercises', function (Blueprint $table): void {
            $table->string('instruction')->nullable()->after('prompt');
            $table->text('hint')->nullable()->after('explanation');
            $table->json('accepted_answers')->nullable()->after('answer');
            $table->json('tiles')->nullable()->after('options');
            $table->string('origin')->default('admin')->after('status');
            $table->string('dedup_key')->nullable()->after('prompt');
            $table->unique(['grammar_rule_id', 'dedup_key']);
        });

        Schema::create('grammar_exercise_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grammar_rule_exercise_id')->constrained('grammar_rule_exercises')->cascadeOnDelete();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'grammar_rule_exercise_id']);
        });

        Schema::create('grammar_exercise_generations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grammar_rule_id')->constrained('grammar_rules')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('size');
            $table->string('status');
            $table->unsignedSmallInteger('created_count')->default(0);
            $table->string('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['grammar_rule_id', 'status']);
            $table->index(['user_id', 'grammar_rule_id', 'created_at']);
        });

        Schema::table('grammar_exam_attempts', function (Blueprint $table): void {
            $table->string('level')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('grammar_exam_attempts', function (Blueprint $table): void {
            $table->dropColumn('level');
        });
        Schema::dropIfExists('grammar_exercise_generations');
        Schema::dropIfExists('grammar_exercise_reports');
        Schema::table('grammar_rule_exercises', function (Blueprint $table): void {
            $table->dropUnique(['grammar_rule_id', 'dedup_key']);
            $table->dropColumn(['instruction', 'hint', 'accepted_answers', 'tiles', 'origin', 'dedup_key']);
        });
    }
};
