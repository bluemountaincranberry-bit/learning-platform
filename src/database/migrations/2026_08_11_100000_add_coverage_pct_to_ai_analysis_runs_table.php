<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_analysis_runs', function (Blueprint $table): void {
            // 0-100, one decimal — same convention as ContentService's progress_pct.
            $table->decimal('coverage_pct', 5, 1)->nullable()->after('config');
            // The transcript's distinct tokenizer-normalized words not covered by
            // any lexeme candidate from this run — persisted (not just the
            // aggregate percentage) so task 9.5 can label the exact leftover
            // words in the UI instead of recomputing coverage with a different
            // heuristic.
            $table->json('uncovered_words')->nullable()->after('coverage_pct');
            // Guards the coverage-triggered auto-retry (task 9.1) to at most one
            // per run — a thorough retry must never itself trigger another retry.
            $table->boolean('retried_for_coverage')->default(false)->after('uncovered_words');
        });
    }

    public function down(): void
    {
        Schema::table('ai_analysis_runs', function (Blueprint $table): void {
            $table->dropColumn(['coverage_pct', 'uncovered_words', 'retried_for_coverage']);
        });
    }
};
