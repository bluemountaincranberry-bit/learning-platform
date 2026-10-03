<?php

namespace App\Modules\Learning\Infrastructure;

use Symfony\Component\Process\Process;

class AudioNormalizationService
{
    /** @return array{path: string, temporary: bool} */
    public function forPronunciation(string $audioPath): array
    {
        if (strtolower(pathinfo($audioPath, PATHINFO_EXTENSION)) === 'wav') {
            return ['path' => $audioPath, 'temporary' => false];
        }

        $normalizedPath = tempnam(sys_get_temp_dir(), 'exercise-wav-').'.wav';
        $process = new Process(['ffmpeg', '-y', '-i', $audioPath, '-ac', '1', '-ar', '16000', '-sample_fmt', 's16', $normalizedPath]);
        $process->setTimeout(30);
        $process->mustRun();

        return ['path' => $normalizedPath, 'temporary' => true];
    }

    public function cleanup(string $path, bool $temporary): void
    {
        if ($temporary && is_file($path)) @unlink($path);
    }
}
