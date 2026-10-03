<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_grammar_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_analysis_run_id')->constrained('ai_analysis_runs')->cascadeOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->text('example')->nullable();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->foreignId('matched_grammar_rule_id')->nullable()->constrained('grammar_rules')->nullOnDelete();
            $table->decimal('match_score', 4, 3)->nullable();
            $table->string('status', 16)->default('pending'); // pending | accepted | rejected | edited | applied
            $table->timestamps();

            $table->index(['ai_analysis_run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_grammar_candidates');
    }
};
