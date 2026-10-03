<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcript_segment_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transcript_segment_id')->constrained('transcript_segments')->cascadeOnDelete();
            $table->string('language', 8);
            $table->text('text');
            $table->string('source', 32)->default('ai');
            $table->timestamps();

            $table->unique(['transcript_segment_id', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_segment_translations');
    }
};
