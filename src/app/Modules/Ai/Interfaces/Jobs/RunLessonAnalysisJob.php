<?php

namespace App\Modules\Ai\Interfaces\Jobs;

use App\Contracts\Ai\AiErrorMessage;
use App\Modules\Ai\Application\LessonAnalysisService;
use App\Modules\Ai\Application\LessonCandidateMatchingService;
use App\Modules\Learning\Application\Contracts\LessonAnalysisStoreInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunLessonAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public int $runId) {}

    public function backoff(): array
    {
        return [30, 90];
    }

    public function tags(): array
    {
        return ['lesson-analysis-run:'.$this->runId, 'job:run-lesson-analysis'];
    }

    public function handle(LessonAnalysisService $service, LessonCandidateMatchingService $matcher, LessonAnalysisStoreInterface $lessons): void
    {
        if (! $lessons->startRun($this->runId)) {
            return;
        }

        try {
            $service->analyze($this->runId);
            try {
                $matcher->matchRun($this->runId);
            } catch (Throwable $e) {
                Log::warning('LessonCandidateMatchingService failed for lesson analysis run', ['run_id' => $this->runId, 'message' => AiErrorMessage::safe($e)]);
            }
            $lessons->completeRun($this->runId);
        } catch (Throwable $e) {
            $lessons->failRun($this->runId, AiErrorMessage::safe($e));
            throw $e;
        }
    }
}
