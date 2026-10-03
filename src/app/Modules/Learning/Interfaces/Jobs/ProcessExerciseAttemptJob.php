<?php

namespace App\Modules\Learning\Interfaces\Jobs;

use App\Modules\Learning\Application\ExerciseAttemptService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessExerciseAttemptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public int $attemptId) {}

    public function backoff(): array
    {
        return [15, 60, 180];
    }

    public function tags(): array
    {
        return ['exercise-attempt:'.$this->attemptId, 'job:process-exercise-attempt'];
    }

    public function handle(ExerciseAttemptService $service): void
    {
        $service->process($this->attemptId);
    }
}
