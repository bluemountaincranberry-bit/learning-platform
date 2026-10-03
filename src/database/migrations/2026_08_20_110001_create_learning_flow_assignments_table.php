<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_flow_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('learning_flow_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('language', 8)->nullable();
            $table->string('level', 2)->nullable();
            $table->string('learning_goal', 32)->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'priority']);
            $table->index(['language', 'level', 'learning_goal', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_flow_assignments');
    }
};
