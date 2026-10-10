<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_practice_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_conversation_id')->unique()->constrained('agent_conversations')->cascadeOnDelete();
            $table->string('mode', 16);
            $table->string('status', 16)->default('active');
            $table->json('question_ids');
            $table->unsignedSmallInteger('question_count');
            $table->string('focus', 120)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_practice_sessions');
    }
};
