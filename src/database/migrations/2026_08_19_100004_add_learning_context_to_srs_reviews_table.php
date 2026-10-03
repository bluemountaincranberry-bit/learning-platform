<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('srs_reviews', function (Blueprint $table): void {
            $table->foreignId('content_lexeme_id')->nullable()->after('srs_card_id')->constrained()->nullOnDelete();
            $table->foreignId('transcript_segment_id')->nullable()->after('content_lexeme_id')->constrained()->nullOnDelete();
            $table->string('exercise_type', 32)->nullable()->after('new_interval');
            $table->string('error_type', 32)->nullable()->after('exercise_type');
            $table->boolean('hint_used')->nullable()->after('error_type');
            $table->json('answer_metadata')->nullable()->after('hint_used');
            $table->index(['content_lexeme_id', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('srs_reviews', function (Blueprint $table): void {
            $table->dropForeign(['content_lexeme_id']);
            $table->dropForeign(['transcript_segment_id']);
            $table->dropIndex(['content_lexeme_id', 'reviewed_at']);
            $table->dropColumn(['content_lexeme_id', 'transcript_segment_id', 'exercise_type', 'error_type', 'hint_used', 'answer_metadata']);
        });
    }
};
