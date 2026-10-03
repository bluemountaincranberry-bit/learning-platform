<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_lexeme_confidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_lexeme_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('recognition')->default(0);
            $table->unsignedTinyInteger('recall')->default(0);
            $table->unsignedTinyInteger('production')->default(0);
            $table->unsignedTinyInteger('listening')->default(0);
            $table->unsignedTinyInteger('speaking')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'content_lexeme_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_lexeme_confidences');
    }
};
