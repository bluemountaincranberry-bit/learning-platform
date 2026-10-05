<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['lesson_lexeme_candidates', 'lesson_grammar_candidates'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('lesson_id')->nullable()->after('lesson_analysis_run_id')->constrained('lessons')->cascadeOnDelete();
                $table->string('source', 16)->default('ai')->after('lesson_id');
                $table->softDeletes();
            });

            DB::table($tableName.' as candidates')
                ->join('lesson_analysis_runs as runs', 'runs.id', '=', 'candidates.lesson_analysis_run_id')
                ->whereNull('candidates.lesson_id')
                ->select('candidates.id', 'runs.lesson_id')
                ->orderBy('candidates.id')
                ->chunk(500, function ($candidates) use ($tableName): void {
                    foreach ($candidates->groupBy('lesson_id') as $lessonId => $items) {
                        DB::table($tableName)
                            ->whereIn('id', $items->pluck('id'))
                            ->update(['lesson_id' => $lessonId]);
                    }
                });

            if (DB::table($tableName)->whereNull('lesson_id')->exists()) {
                throw new RuntimeException("Cannot migrate {$tableName}: an item is not linked to a lesson.");
            }

            // Existing rows all point to a run, but future hand-created items
            // remain attached to the lesson when a run is removed.
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('lesson_id')->nullable(false)->change();
                $table->dropForeign(['lesson_analysis_run_id']);
                $table->foreignId('lesson_analysis_run_id')->nullable()->change();
                $table->foreign('lesson_analysis_run_id')->references('id')->on('lesson_analysis_runs')->nullOnDelete();
                $table->index(['lesson_id', 'deleted_at']);
            });
        }

        Schema::create('lesson_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->text('original_text');
            $table->text('corrected_text');
            $table->text('explanation')->nullable();
            $table->string('source', 16)->default('manual');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['lesson_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_corrections');

        foreach (['lesson_lexeme_candidates', 'lesson_grammar_candidates'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['lesson_id']);
                $table->dropForeign(['lesson_analysis_run_id']);
                $table->dropIndex(['lesson_id', 'deleted_at']);
                $table->dropColumn(['lesson_id', 'source', 'deleted_at']);
                $table->foreign('lesson_analysis_run_id')->references('id')->on('lesson_analysis_runs')->cascadeOnDelete();
            });
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('lesson_analysis_run_id')->nullable(false)->change();
            });
        }
    }
};
