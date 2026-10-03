<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_flow_metric_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('learning_flow_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('content_lexeme_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 32);
            $table->string('activity_type', 32)->nullable();
            $table->string('dimension', 32)->nullable();
            $table->boolean('success')->nullable();
            $table->unsignedSmallInteger('points')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['event_type', 'created_at']);
            $table->index(['learning_flow_profile_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_flow_metric_events');
    }
};
