<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_lexeme_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('content_lexeme_id')->constrained('content_lexemes')->cascadeOnDelete();
            $table->timestamp('learned_at');
            $table->timestamps();

            $table->unique(['user_id', 'content_lexeme_id']);
            $table->index(['content_lexeme_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_lexeme_progress');
    }
};
