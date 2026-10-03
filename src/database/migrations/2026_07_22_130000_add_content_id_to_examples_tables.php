<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable: null means a globally curated example (admin-authored, shown
     * regardless of context); a set value means the example was sourced from
     * (or is specifically relevant to) that content item — e.g. examples
     * attached by AiCandidateApplyService from an AI analysis run.
     */
    public function up(): void
    {
        Schema::table('lexeme_examples', function (Blueprint $table): void {
            $table->foreignId('content_id')->nullable()->after('lexeme_id')->constrained('contents')->nullOnDelete();
            $table->index('content_id');
        });

        Schema::table('grammar_rule_examples', function (Blueprint $table): void {
            $table->foreignId('content_id')->nullable()->after('grammar_rule_id')->constrained('contents')->nullOnDelete();
            $table->index('content_id');
        });
    }

    public function down(): void
    {
        Schema::table('lexeme_examples', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('content_id');
        });

        Schema::table('grammar_rule_examples', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('content_id');
        });
    }
};
