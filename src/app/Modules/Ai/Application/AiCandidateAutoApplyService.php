<?php

namespace App\Modules\Ai\Application;

use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Application\Contracts\CandidateModerationInterface;
use App\Modules\Content\Application\Contracts\ContentAnalysisSourceReaderInterface;

/**
 * Automatically moderates candidates before applying accepted items to the catalog.
 */
class AiCandidateAutoApplyService
{
    public function __construct(
        private readonly CandidateModerationInterface $moderation,
        private readonly AiCandidateApplyService $applyService,
        private readonly ContentAnalysisSourceReaderInterface $contentSources,
    ) {}

    /** @return array{applied: array{lexemes: int, grammar: int}} */
    public function autoApply(AiAnalysisRun $run): array
    {
        $content = $this->contentSources->get((int) $run->content_id);
        if ($content === null) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException;
        }

        $this->moderation->acceptEligibleForRun(
            (int) $run->id,
            (string) $content->sourceText,
            $content->level,
            $run->config ?? [],
        );

        return ['applied' => $this->applyService->apply($run)];
    }
}
