<?php

namespace App\Modules\Ai\Application;

use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Ai\Interfaces\Jobs\ComputeGrammarRuleEmbeddingsJob;
use App\Modules\Content\Application\Contracts\AcceptedCandidateWriterInterface;
use App\Modules\Content\Application\Contracts\CandidateApplicationInterface;

class AiCandidateApplyService implements CandidateApplicationInterface
{
    public function __construct(private readonly AcceptedCandidateWriterInterface $candidateWriter) {}

    /** @return array{lexemes:int, grammar:int} */
    public function apply(object $run): array
    {
        if (! $run instanceof AiAnalysisRun) {
            throw new \InvalidArgumentException('Candidate application requires an AI analysis run.');
        }

        $translationLanguage = $run->config['translation_language'] ?? config('ai.analysis.translation_language', 'ru');

        return $this->candidateWriter->apply(
            $run->id,
            $run->content_id,
            $translationLanguage,
            static fn (int $ruleId) => ComputeGrammarRuleEmbeddingsJob::dispatch([$ruleId]),
        );
    }
}
