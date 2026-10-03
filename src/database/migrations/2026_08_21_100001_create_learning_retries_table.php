<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_retries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_lexeme_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_exercise_attempt_id')->nullable()->constrained('exercise_attempts')->nullOnDelete();
            $table->foreignId('source_srs_review_id')->nullable()->constrained('srs_reviews')->nullOnDelete();
            $table->string('activity_type', 32);
            $table->string('error_type', 64)->nullable();
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->timestamp('available_at');
            $table->string('status', 16)->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'available_at']);
            $table->index(['user_id', 'content_lexeme_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_retries');
    }
};
