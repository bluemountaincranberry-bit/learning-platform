<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contents', function (Blueprint $table): void {
            $table->timestamp('processing_requested_at')->nullable()->after('processing_failure_reason');
            $table->timestamp('processing_completed_at')->nullable()->after('processing_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('contents', function (Blueprint $table): void {
            $table->dropColumn(['processing_requested_at', 'processing_completed_at']);
        });
    }
};
