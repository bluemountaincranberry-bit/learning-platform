<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcript_segment_lexemes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transcript_segment_id')->constrained('transcript_segments')->cascadeOnDelete();
            $table->foreignId('content_lexeme_id')->constrained('content_lexemes')->cascadeOnDelete();
            $table->unsignedInteger('start_offset');
            $table->unsignedInteger('end_offset');
            $table->string('surface_text');
            $table->string('match_type', 16)->default('exact');
            $table->decimal('confidence', 4, 3)->nullable();
            $table->timestamps();

            $table->unique(['transcript_segment_id', 'content_lexeme_id', 'start_offset']);
            $table->index(['content_lexeme_id', 'transcript_segment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_segment_lexemes');
    }
};
