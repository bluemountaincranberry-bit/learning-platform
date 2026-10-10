<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('speaking_mistakes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('language', 16)->default('en');
            $table->string('native_language', 16)->default('ru');
            $table->text('prompt_text')->nullable();
            $table->text('original_text');
            $table->text('corrected_text');
            $table->text('explanation')->nullable();
            $table->string('category', 32)->default('general');
            $table->string('source_type', 32)->default('speaking_practice');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('status', 16)->default('active');
            $table->string('confidence', 16)->default('clear');
            $table->unsignedTinyInteger('consecutive_correct')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'language', 'status', 'last_seen_at'], 'speaking_mistakes_user_active_idx');
            $table->index(['user_id', 'category', 'created_at'], 'speaking_mistakes_user_category_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speaking_mistakes');
    }
};
