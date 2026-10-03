<?php

namespace App\Contracts\Ai;

use App\Modules\Ai\Domain\Models\AiAnalysisRun;

interface ContentAnalysisCapability
{
    public function analyze(AiAnalysisRun $run): void;
}
