<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_learning_preferences', function (Blueprint $table): void {
            $table->foreignId('learning_flow_profile_id')->nullable()->after('user_id')->constrained('learning_flow_profiles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_learning_preferences', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('learning_flow_profile_id');
        });
    }
};
