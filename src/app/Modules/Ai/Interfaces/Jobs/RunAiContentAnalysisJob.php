<?php

namespace App\Modules\Ai\Interfaces\Jobs;

use App\Modules\Ai\Application\AiCandidateAutoApplyService;
use App\Modules\Ai\Application\CandidateMatchingService;
use App\Contracts\Ai\AiErrorMessage;
use App\Contracts\Ai\ContentAnalysisCapability;
use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Application\Contracts\TranscriptLexemeLinkerInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunAiContentAnalysisJob implements ShouldQueue
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
        return ['ai-analysis-run:'.$this->runId, 'job:run-ai-content-analysis'];
    }

    public function handle(ContentAnalysisCapability $service, CandidateMatchingService $matcher, AiCandidateAutoApplyService $autoApplyService, TranscriptLexemeLinkerInterface $segmentLinker): void
    {
        $run = AiAnalysisRun::query()->find($this->runId);
        if (! $run || $run->status !== AiAnalysisRun::STATUS_PENDING) {
            return;
        }
        $run->update(['status' => AiAnalysisRun::STATUS_RUNNING, 'started_at' => now()]);

        try {
            $service->analyze($run);
            try {
                $matcher->matchRun($run);
            } catch (\Throwable $e) {
                Log::warning('CandidateMatchingService failed for analysis run', ['run_id' => $run->id, 'message' => AiErrorMessage::safe($e)]);
            }
            try {
                $autoApplyService->autoApply($run);
                $segmentLinker->linkForContent($run->content_id);
            } catch (\Throwable $e) {
                Log::warning('AiCandidateAutoApplyService failed for analysis run', ['run_id' => $run->id, 'message' => AiErrorMessage::safe($e)]);
            }
            $run->update(['status' => AiAnalysisRun::STATUS_COMPLETED, 'completed_at' => now()]);
            \App\Modules\Infrastructure\Domain\Models\OutboxEvent::record(\App\Modules\Content\Domain\Events\ContentAnalysisCompleted::class, 'content', $run->content_id, ['content_id' => $run->content_id, 'analysis_run_id' => $run->id]);
            \App\Modules\Content\Domain\Events\ContentAnalysisCompleted::dispatch((int) $run->content_id, (int) $run->id);
        } catch (\Throwable $e) {
            $run->update(['status' => AiAnalysisRun::STATUS_FAILED, 'completed_at' => now(), 'failure_reason' => AiErrorMessage::safe($e)]);
            throw $e;
        }
    }
}
