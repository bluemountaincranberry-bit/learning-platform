<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_learning_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('session_minutes')->nullable();
            $table->unsignedTinyInteger('daily_new_words')->nullable();
            $table->unsignedTinyInteger('listening_weight')->nullable();
            $table->unsignedTinyInteger('speaking_weight')->nullable();
            $table->string('hint_mode', 16)->nullable();
            $table->string('difficulty_preference', 16)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_learning_preferences');
    }
};
