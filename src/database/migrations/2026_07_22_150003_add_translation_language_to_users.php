<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distinct from the existing `ui_language`, which means "language of
     * content this user studies" (see RecommendationService). This is the
     * admin's default target language for AI-generated translations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('translation_language', 8)->nullable()->after('ui_language');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('translation_language');
        });
    }
};
