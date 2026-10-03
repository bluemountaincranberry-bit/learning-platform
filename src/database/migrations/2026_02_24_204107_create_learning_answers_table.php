<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('learning_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_session_id')->constrained('learning_sessions')->cascadeOnDelete();
            $table->string('item_key');
            $table->string('result');
            $table->unsignedTinyInteger('score')->default(0);
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['learning_session_id', 'item_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_answers');
    }
};
