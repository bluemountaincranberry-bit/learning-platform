<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grammar_rules', function (Blueprint $table): void {
            $table->foreignId('owner_user_id')->nullable()->after('status')->constrained('users')->cascadeOnDelete();
            $table->foreignId('source_lesson_id')->nullable()->after('owner_user_id')->constrained('lessons')->nullOnDelete();
            $table->string('source_lesson_title')->nullable()->after('source_lesson_id');
            $table->index(['owner_user_id', 'language', 'status']);
            $table->unsignedBigInteger('topic_id')->nullable()->change();
        });

        Schema::table('lesson_grammar_candidates', function (Blueprint $table): void {
            $table->foreignId('personal_grammar_rule_id')->nullable()->after('matched_grammar_rule_id')
                ->constrained('grammar_rules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('grammar_rules')->whereNotNull('owner_user_id')->exists()) {
            throw new RuntimeException('Cannot remove personal grammar rules while learners have saved rules.');
        }

        Schema::table('lesson_grammar_candidates', function (Blueprint $table): void {
            $table->dropForeign(['personal_grammar_rule_id']);
            $table->dropColumn('personal_grammar_rule_id');
        });

        Schema::table('grammar_rules', function (Blueprint $table): void {
            $table->dropForeign(['source_lesson_id']);
            $table->dropForeign(['owner_user_id']);
            $table->dropIndex(['owner_user_id', 'language', 'status']);
            $table->dropColumn(['owner_user_id', 'source_lesson_id', 'source_lesson_title']);
            $table->unsignedBigInteger('topic_id')->nullable(false)->change();
        });
    }
};
