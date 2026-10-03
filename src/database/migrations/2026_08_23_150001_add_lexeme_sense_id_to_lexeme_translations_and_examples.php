<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Task 10.3: nullable — NULL means "not yet tagged with a specific
        // meaning" (every row created before this migration, plus any future
        // one whose candidate had no `sense` gloss), not an error. Existing
        // rows are left untouched, no backfill.
        Schema::table('lexeme_translations', function (Blueprint $table): void {
            $table->foreignId('lexeme_sense_id')->nullable()->after('lexeme_id')->constrained('lexeme_senses')->nullOnDelete();
        });

        Schema::table('lexeme_examples', function (Blueprint $table): void {
            $table->foreignId('lexeme_sense_id')->nullable()->after('lexeme_id')->constrained('lexeme_senses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lexeme_translations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('lexeme_sense_id');
        });

        Schema::table('lexeme_examples', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('lexeme_sense_id');
        });
    }
};
