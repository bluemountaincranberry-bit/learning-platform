<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_lexemes', function (Blueprint $table): void {
            $table->foreignId('lexeme_id')->nullable()->after('content_id')->constrained('lexemes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('content_lexemes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('lexeme_id');
        });
    }
};
