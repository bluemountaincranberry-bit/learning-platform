<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcript_segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_id')->constrained('contents')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->unsignedBigInteger('start_ms');
            $table->unsignedBigInteger('end_ms')->nullable();
            $table->text('text');
            $table->string('language', 8)->nullable();
            $table->string('source', 32)->default('youtube');
            $table->string('source_key')->nullable();
            $table->timestamps();

            $table->unique(['content_id', 'sequence']);
            $table->index(['content_id', 'start_ms']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_segments');
    }
};
