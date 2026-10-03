<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_lexeme_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transcript_segment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('exercise_type', 32);
            $table->string('status', 16)->default('pending');
            $table->text('target_text');
            $table->text('user_text')->nullable();
            $table->string('error_type', 32)->nullable();
            $table->boolean('is_correct')->nullable();
            $table->boolean('hint_used')->default(false);
            $table->unsignedSmallInteger('replay_count')->default(0);
            $table->unsignedTinyInteger('score')->nullable();
            $table->json('provider_result')->nullable();
            $table->string('audio_disk', 32)->nullable();
            $table->string('audio_path')->nullable();
            $table->timestamp('audio_expires_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'exercise_type', 'created_at']);
            $table->index(['content_lexeme_id', 'status']);
        });
    }

    public function down(): void { Schema::dropIfExists('exercise_attempts'); }
};
