<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_messages', function (Blueprint $table): void {
            $table->string('voice_audio_disk', 32)->nullable();
            $table->string('voice_audio_path')->nullable();
            $table->timestamp('voice_audio_expires_at')->nullable();
            $table->boolean('voice_audio_pinned')->default(false);
            $table->string('transcription_provider', 24)->nullable();
            $table->string('transcription_language', 8)->nullable();
            $table->index(['voice_audio_expires_at', 'voice_audio_pinned'], 'agent_messages_voice_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::table('agent_messages', function (Blueprint $table): void {
            $table->dropIndex('agent_messages_voice_expiry_idx');
            $table->dropColumn([
                'voice_audio_disk', 'voice_audio_path', 'voice_audio_expires_at',
                'voice_audio_pinned', 'transcription_provider', 'transcription_language',
            ]);
        });
    }
};
