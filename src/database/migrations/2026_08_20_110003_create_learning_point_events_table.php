<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_point_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_lexeme_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_type', 32);
            $table->unsignedBigInteger('source_id');
            $table->string('source_key')->unique();
            $table->string('activity_type', 32);
            $table->string('reason', 64);
            $table->unsignedSmallInteger('base_points');
            $table->decimal('multiplier', 4, 2)->default(1);
            $table->unsignedSmallInteger('points');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'activity_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_point_events');
    }
};
