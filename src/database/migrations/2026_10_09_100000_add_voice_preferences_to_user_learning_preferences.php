<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_learning_preferences', function (Blueprint $table): void {
            $table->string('speech_transcription_provider', 24)->nullable();
            $table->string('speech_audio_retention', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_learning_preferences', function (Blueprint $table): void {
            $table->dropColumn(['speech_transcription_provider', 'speech_audio_retention']);
        });
    }
};
