<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_lexeme_candidates', function (Blueprint $table): void {
            // Task 9.9: 2-3 examples per candidate instead of one, each
            // tagged 'context' (grounded in this content's actual transcript)
            // or 'generated'. The existing singular example/example_translation
            // columns are kept (not dropped) as a backward-compat mirror of
            // the primary example — Filament's LexemeCandidatesRelationManager
            // table and other simple readers still use them directly.
            $table->json('examples')->nullable()->after('example_translation');
        });
    }

    public function down(): void
    {
        Schema::table('content_lexeme_candidates', function (Blueprint $table): void {
            $table->dropColumn('examples');
        });
    }
};
