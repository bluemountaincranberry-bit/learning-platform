<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_lexeme_progress', function (Blueprint $table): void {
            $table->foreignId('lexeme_id')->nullable()->after('content_lexeme_id')->constrained('lexemes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_lexeme_progress', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('lexeme_id');
        });
    }
};
