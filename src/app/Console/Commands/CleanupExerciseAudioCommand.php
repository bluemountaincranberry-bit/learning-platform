<?php

namespace App\Console\Commands;

use App\Modules\Learning\Domain\Models\ExerciseAttempt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupExerciseAudioCommand extends Command
{
    protected $signature = 'learning:cleanup-exercise-audio {--hours=1 : Delete files older than this many hours}';
    protected $description = 'Delete expired temporary audio files from exercise attempts';

    public function handle(): int
    {
        $deleted = 0;
        ExerciseAttempt::query()->whereNotNull('audio_path')
            ->where('audio_expires_at', '<', now()->subHours((int) $this->option('hours')))
            ->chunkById(100, function ($attempts) use (&$deleted): void {
                foreach ($attempts as $attempt) {
                    Storage::disk($attempt->audio_disk ?: 'local')->delete($attempt->audio_path);
                    $attempt->update(['audio_path' => null, 'audio_disk' => null]);
                    $deleted++;
                }
            });

        $this->info("Deleted {$deleted} expired exercise audio file(s).");
        return self::SUCCESS;
    }
}
