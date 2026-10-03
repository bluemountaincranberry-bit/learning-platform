<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Self-reported CEFR level (Content::CEFR_LEVELS). A soft learner
     * signal, not curated content difficulty — see Content.level for that.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('current_level', 2)->nullable()->after('translation_language');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('current_level');
        });
    }
};
