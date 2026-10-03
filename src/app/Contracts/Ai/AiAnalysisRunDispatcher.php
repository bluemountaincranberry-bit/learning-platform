<?php

namespace App\Contracts\Ai;

interface AiAnalysisRunDispatcher
{
    public function hasActiveRun(int $contentId): bool;

    public function start(int $contentId, AiAnalysisRunConfig $config): void;
}
