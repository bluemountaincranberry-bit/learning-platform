<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distinguishes ProcessContentJob's naive tokenizer rows ('tokenizer')
     * from AiCandidateApplyService's enriched rows ('ai') — both currently
     * land in the same table with no way to tell them apart, which is why
     * the Words tab shows AI-picked words drowned in hundreds of bare
     * tokenizer entries. Backfilled to 'tokenizer' for existing rows: a
     * best-effort default, not a precise reconstruction — any row that was
     * actually AI-applied before this migration will show under "From text"
     * until that content's analysis is re-run.
     */
    public function up(): void
    {
        Schema::table('content_lexemes', function (Blueprint $table): void {
            $table->string('origin', 16)->nullable()->after('type');
        });

        DB::table('content_lexemes')->whereNull('origin')->update(['origin' => 'tokenizer']);
    }

    public function down(): void
    {
        Schema::table('content_lexemes', function (Blueprint $table): void {
            $table->dropColumn('origin');
        });
    }
};
