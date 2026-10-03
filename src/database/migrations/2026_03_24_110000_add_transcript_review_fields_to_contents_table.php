<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contents', function (Blueprint $table): void {
            $table->timestamp('transcript_accepted_at')->nullable()->after('processing_completed_at');
            $table->foreignId('transcript_accepted_by')->nullable()->after('transcript_accepted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('analysis_stale_at')->nullable()->after('transcript_accepted_by');
        });
    }

    public function down(): void
    {
        Schema::table('contents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('transcript_accepted_by');
            $table->dropColumn(['transcript_accepted_at', 'analysis_stale_at']);
        });
    }
};
