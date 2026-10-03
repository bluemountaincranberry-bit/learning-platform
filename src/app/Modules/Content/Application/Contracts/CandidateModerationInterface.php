<?php

namespace App\Modules\Content\Application\Contracts;

interface CandidateModerationInterface
{
    public function acceptEligibleForRun(int $runId, string $sourceText, ?string $contentLevel, array $config): void;
}
