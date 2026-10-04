<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * VIK-39: AI-generated grammar rule examples.
 *
 * - grammar_rule_examples gains `origin` (admin | ai | content), `kind`
 *   (affirmative | negative | question | mistake), `target_spans` (the
 *   grammar form inside `example`, as [start, end) character offsets —
 *   code points, not bytes) and `mistake` (the typical wrong sentence for
 *   kind = mistake). Existing rows stay origin = admin.
 * - grammar_rule_example_generations: one row per queued AI batch, for
 *   "one batch per rule at a time", the per-learner daily limit and
 *   status polling (same shape as grammar_exercise_generations, VIK-31).
 * - grammar_rule_example_hides: a learner hides a bad example for
 *   themselves; the shared catalog row stays.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grammar_rule_examples', function (Blueprint $table): void {
            $table->string('origin', 16)->default('admin')->after('content_id');
            $table->string('kind', 16)->nullable()->after('origin');
            $table->json('target_spans')->nullable()->after('example');
            $table->text('mistake')->nullable()->after('target_spans');
        });

        DB::table('grammar_rule_examples')->whereNotNull('content_id')->update(['origin' => 'content']);

        Schema::create('grammar_rule_example_generations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grammar_rule_id')->constrained('grammar_rules')->cascadeOnDelete();
            // Null for system batches (backfill command): no daily limit.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('size');
            $table->string('translation_language', 8)->nullable();
            $table->string('status', 16);
            $table->unsignedSmallInteger('created_count')->default(0);
            $table->string('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['grammar_rule_id', 'status']);
            $table->index(['user_id', 'grammar_rule_id', 'created_at']);
        });

        Schema::create('grammar_rule_example_hides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grammar_rule_example_id')->constrained('grammar_rule_examples')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'grammar_rule_example_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grammar_rule_example_hides');
        Schema::dropIfExists('grammar_rule_example_generations');

        Schema::table('grammar_rule_examples', function (Blueprint $table): void {
            $table->dropColumn(['origin', 'kind', 'target_spans', 'mistake']);
        });
    }
};
