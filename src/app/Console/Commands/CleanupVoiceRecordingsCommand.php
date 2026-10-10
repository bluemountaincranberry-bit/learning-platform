<?php

namespace App\Console\Commands;

use App\Modules\Ai\Domain\Models\AgentMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupVoiceRecordingsCommand extends Command
{
    protected $signature = 'ai:cleanup-voice-recordings';

    protected $description = 'Delete expired, unpinned voice recordings attached to agent messages';

    public function handle(): int
    {
        $deleted = 0;
        AgentMessage::query()->whereNotNull('voice_audio_path')
            ->where('voice_audio_pinned', false)
            ->whereNotNull('voice_audio_expires_at')
            ->where('voice_audio_expires_at', '<=', now())
            ->chunkById(100, function ($messages) use (&$deleted): void {
                foreach ($messages as $message) {
                    Storage::disk($message->voice_audio_disk ?: 'local')->delete($message->voice_audio_path);
                    $message->forceFill(['voice_audio_path' => null, 'voice_audio_disk' => null])->save();
                    $deleted++;
                }
            });

        $this->info("Deleted {$deleted} expired voice recording(s).");

        return self::SUCCESS;
    }
}
