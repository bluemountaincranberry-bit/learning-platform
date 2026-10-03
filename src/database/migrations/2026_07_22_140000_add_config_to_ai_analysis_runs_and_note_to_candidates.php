<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_analysis_runs', function (Blueprint $table): void {
            $table->json('config')->nullable()->after('model');
        });

        Schema::table('content_lexeme_candidates', function (Blueprint $table): void {
            $table->text('note')->nullable()->after('confidence');
        });

        Schema::table('content_grammar_candidates', function (Blueprint $table): void {
            $table->text('note')->nullable()->after('confidence');
        });
    }

    public function down(): void
    {
        Schema::table('ai_analysis_runs', function (Blueprint $table): void {
            $table->dropColumn('config');
        });

        Schema::table('content_lexeme_candidates', function (Blueprint $table): void {
            $table->dropColumn('note');
        });

        Schema::table('content_grammar_candidates', function (Blueprint $table): void {
            $table->dropColumn('note');
        });
    }
};
