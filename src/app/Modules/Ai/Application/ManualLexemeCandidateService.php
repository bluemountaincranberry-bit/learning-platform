<?php

namespace App\Modules\Ai\Application;

use App\Contracts\Ai\ManualLexemeCandidateCapability;
use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Application\Contracts\CandidateApplicationInterface;
use App\Modules\Content\Application\Contracts\ManualLexemeCandidateStoreInterface;
use Illuminate\Support\Facades\DB;

final class ManualLexemeCandidateService implements ManualLexemeCandidateCapability
{
    public function __construct(
        private readonly AiExplainLexemeService $explanation,
        private readonly CandidateMatchingService $matching,
        private readonly CandidateApplicationInterface $application,
        private readonly ManualLexemeCandidateStoreInterface $candidates,
    ) {}

    public function analyzeAndApply(int $contentId, string $text, string $sentence, string $sourceLanguage, string $translationLanguage): int
    {
        $analysis = $this->explanation->analyzeForManualAdd($text, $sentence, $sourceLanguage, $translationLanguage);

        return DB::transaction(function () use ($contentId, $text, $translationLanguage, $analysis): int {
            $run = AiAnalysisRun::query()->create([
                'content_id' => $contentId,
                'status' => AiAnalysisRun::STATUS_COMPLETED,
                'config' => ['trigger' => 'manual_add', 'translation_language' => $translationLanguage],
                'completed_at' => now(),
            ]);

            $candidateId = $this->candidates->create($run->id, $text, $analysis);
            $this->matching->matchRun($run);
            $this->candidates->accept($candidateId);
            $this->application->apply($run);

            return $this->candidates->markManualOccurrence($contentId, $text);
        });
    }
}
