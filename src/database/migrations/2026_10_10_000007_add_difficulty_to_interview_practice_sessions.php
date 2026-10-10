<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interview_practice_sessions', function (Blueprint $table): void {
            $table->string('difficulty', 16)->default('any');
        });
    }

    public function down(): void
    {
        Schema::table('interview_practice_sessions', function (Blueprint $table): void {
            $table->dropColumn('difficulty');
        });
    }
};
