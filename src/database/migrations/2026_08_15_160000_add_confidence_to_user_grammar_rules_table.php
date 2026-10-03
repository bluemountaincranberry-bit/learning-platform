<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two independent confidence values, not one blended field (explicit
     * product decision): confidence_manual is the learner's own self-rating,
     * confidence_calculated is derived by GrammarConfidenceService from SRS
     * review outcomes and grammar_exam_attempts. Neither overwrites the
     * other, so the UI can show "you think X%, practice says Y%" side by
     * side instead of silently picking a winner.
     */
    public function up(): void
    {
        Schema::table('user_grammar_rules', function (Blueprint $table): void {
            $table->decimal('confidence_manual', 5, 2)->nullable()->after('status');
            $table->decimal('confidence_calculated', 5, 2)->nullable()->after('confidence_manual');
            $table->timestamp('confidence_calculated_at')->nullable()->after('confidence_calculated');
        });
    }

    public function down(): void
    {
        Schema::table('user_grammar_rules', function (Blueprint $table): void {
            $table->dropColumn(['confidence_manual', 'confidence_calculated', 'confidence_calculated_at']);
        });
    }
};
