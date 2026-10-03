<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('user_lexeme_progress', 'lexeme_id')) {
            return;
        }

        Schema::table('user_lexeme_progress', function (Blueprint $table): void {
            $table->dropIndex(['lexeme_id', 'user_id']);
            $table->dropConstrainedForeignId('lexeme_id');
        });
    }

    public function down(): void
    {
        //
    }
};
