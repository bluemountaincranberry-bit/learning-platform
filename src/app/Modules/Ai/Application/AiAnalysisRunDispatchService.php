<?php

namespace App\Modules\Ai\Application;

use App\Contracts\Ai\AiAnalysisRunConfig;
use App\Contracts\Ai\AiAnalysisRunDispatcher;
use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Ai\Interfaces\Jobs\RunAiContentAnalysisJob;

final class AiAnalysisRunDispatchService implements AiAnalysisRunDispatcher
{
    public function hasActiveRun(int $contentId): bool
    {
        return AiAnalysisRun::query()
            ->where('content_id', $contentId)
            ->whereIn('status', [AiAnalysisRun::STATUS_PENDING, AiAnalysisRun::STATUS_RUNNING])
            ->exists();
    }

    public function start(int $contentId, AiAnalysisRunConfig $config): void
    {
        $run = AiAnalysisRun::query()->create([
            'content_id' => $contentId,
            'status' => AiAnalysisRun::STATUS_PENDING,
            'config' => $config->toArray(),
        ]);

        RunAiContentAnalysisJob::dispatch($run->id);
    }
}
