<?php

namespace App\Modules\Ai\Interfaces\Jobs;

use App\Contracts\Ai\AiErrorMessage;
use App\Contracts\Ai\LessonAnalysisStoreInterface;
use App\Modules\Ai\Application\LessonAnalysisService;
use App\Modules\Ai\Application\LessonCandidateMatchingService;
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

    // Long notes/PDFs are analyzed in parts, one provider call each (VIK-70),
    // so the deadline covers several sequential calls. The redis queue's
    // retry_after must stay above this or the job is redelivered mid-run.
    public int $timeout = 600;

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
        if ($this->attempts() > $this->tries || ! $lessons->startRun($this->runId, $this->attempts() > 1)) {
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
