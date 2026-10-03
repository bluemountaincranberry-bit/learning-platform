<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_lexemes', function (Blueprint $table): void {
            // Task 10.3: which meaning of the canonical lemma this specific
            // occurrence was used with (the "selected sense" of the model
            // proposal) — nullable, same "not yet tagged" convention as on
            // lexeme_translations/lexeme_examples.
            $table->foreignId('lexeme_sense_id')->nullable()->after('lexeme_id')->constrained('lexeme_senses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('content_lexemes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('lexeme_sense_id');
        });
    }
};
