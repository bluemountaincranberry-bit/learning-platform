<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Defensive: a content_lexemes row that never got synced to a canonical
        // lexeme (e.g. an old failed sync) would leave lexeme_id null here too.
        // Expected to affect ~0 rows; can't be repaired automatically, so drop
        // rather than block the NOT NULL constraint below.
        DB::table('user_lexeme_progress')->whereNull('lexeme_id')->delete();

        Schema::table('user_lexeme_progress', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'content_lexeme_id']);
        });

        Schema::table('user_lexeme_progress', function (Blueprint $table): void {
            $table->unsignedBigInteger('lexeme_id')->nullable(false)->change();
            $table->unique(['user_id', 'lexeme_id']);
            $table->index(['lexeme_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('user_lexeme_progress', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'lexeme_id']);
            $table->dropIndex(['lexeme_id', 'user_id']);
            $table->unsignedBigInteger('lexeme_id')->nullable()->change();
            $table->unique(['user_id', 'content_lexeme_id']);
        });
    }
};
