<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Self-reported preference for how many words AiAnalysisAutoDispatchService
     * should try to extract from content this user submits — one of
     * AiAnalysisRunConfig::THOROUGHNESS_LEVELS. Null means "use the current
     * default" (thorough), so existing users see no behavior change.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('ai_extraction_thoroughness', 16)->nullable()->after('current_level');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('ai_extraction_thoroughness');
        });
    }
};
