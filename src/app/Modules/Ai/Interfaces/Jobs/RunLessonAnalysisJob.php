<?php

namespace App\Modules\Ai\Interfaces\Jobs;

use App\Modules\Ai\Application\LessonAnalysisService;
use App\Modules\Ai\Application\LessonCandidateMatchingService;
use App\Contracts\Ai\AiErrorMessage;
use App\Modules\Ai\Domain\Models\LessonAnalysisRun;
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

    public function handle(LessonAnalysisService $service, LessonCandidateMatchingService $matcher): void
    {
        $run = LessonAnalysisRun::query()->find($this->runId);
        if (! $run || $run->status !== LessonAnalysisRun::STATUS_PENDING) {
            return;
        }
        $run->update(['status' => LessonAnalysisRun::STATUS_RUNNING, 'started_at' => now()]);

        try {
            $service->analyze($run);
            try {
                $matcher->matchRun($run);
            } catch (Throwable $e) {
                Log::warning('LessonCandidateMatchingService failed for lesson analysis run', ['run_id' => $run->id, 'message' => AiErrorMessage::safe($e)]);
            }
            $run->update(['status' => LessonAnalysisRun::STATUS_COMPLETED, 'completed_at' => now()]);
        } catch (Throwable $e) {
            $run->update(['status' => LessonAnalysisRun::STATUS_FAILED, 'completed_at' => now(), 'failure_reason' => AiErrorMessage::safe($e)]);
            throw $e;
        }
    }
}
