<?php

namespace App\Modules\Ai\Application;

use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Application\Contracts\ContentResetOperationsInterface;
use Illuminate\Support\Facades\DB;

final class AiContentResetService
{
    public function __construct(private readonly ContentResetOperationsInterface $contentReset) {}

    public const MODE_HISTORY = 'history';

    public const MODE_AI_RESULTS = 'ai_results';

    public const MODE_FULL_CONTENT = 'full_content';

    /**
     * Deletes the selected scope and starts a fresh analysis/pipeline.
     * Published canonical lexemes are never deleted by this service.
     *
     * @return array{mode: string, deleted_runs: int, deleted_content_lexemes: int, preserved_content_lexemes: int}
     */
    public function resetAndRerun(int $contentId, string $mode): array
    {
        if (! in_array($mode, [self::MODE_HISTORY, self::MODE_AI_RESULTS, self::MODE_FULL_CONTENT], true)) {
            throw new \InvalidArgumentException('Unknown AI reset mode.');
        }

        $runs = AiAnalysisRun::query()->where('content_id', $contentId);

        if ((clone $runs)->whereIn('status', [AiAnalysisRun::STATUS_PENDING, AiAnalysisRun::STATUS_RUNNING])->exists()) {
            throw new \RuntimeException('An AI analysis is already in progress for this content.');
        }

        $result = DB::transaction(function () use ($contentId, $mode, $runs): array {
            $deletedRuns = (clone $runs)->count();
            $deletedContentLexemes = 0;
            $preservedContentLexemes = 0;

            if ($mode === self::MODE_AI_RESULTS) {
                $counts = $this->contentReset->removeUnlearnedAiLexemes($contentId);
                $deletedContentLexemes = $counts['deleted'];
                $preservedContentLexemes = $counts['preserved'];
            }

            if ($mode === self::MODE_FULL_CONTENT) {
                $deletedContentLexemes = $this->contentReset->resetFullContent($contentId);
            }

            $runs->delete();

            return compact('deletedRuns', 'deletedContentLexemes', 'preservedContentLexemes') + ['mode' => $mode];
        });

        $this->contentReset->rerun($contentId, $mode === self::MODE_FULL_CONTENT);

        return [
            'mode' => $result['mode'],
            'deleted_runs' => $result['deletedRuns'],
            'deleted_content_lexemes' => $result['deletedContentLexemes'],
            'preserved_content_lexemes' => $result['preservedContentLexemes'],
        ];
    }
}
