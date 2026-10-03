<?php

namespace App\Modules\Ai\Interfaces\Jobs;

use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Ai\Application\Agent\Graph\AiAnalysisGraphService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunAiAnalysisGraphJob implements ShouldQueue
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
        return ['ai-analysis-run:'.$this->runId, 'job:run-ai-analysis-graph'];
    }

    public function handle(AiAnalysisGraphService $service): void
    {
        $run = AiAnalysisRun::query()->find($this->runId);
        if (! $run || $run->status !== AiAnalysisRun::STATUS_PENDING) {
            return;
        }
        $service->start($run);
    }
}
