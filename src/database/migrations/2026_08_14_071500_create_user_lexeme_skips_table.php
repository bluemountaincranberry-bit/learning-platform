<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Not interested" marker, per canonical Lexeme per user — same shape
     * as user_lexeme_progress's "learned" marker, but the opposite intent:
     * hide this word from the learner's own word list instead of the
     * pipeline pre-curating it away from everyone.
     */
    public function up(): void
    {
        Schema::create('user_lexeme_skips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lexeme_id')->constrained('lexemes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'lexeme_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_lexeme_skips');
    }
};
