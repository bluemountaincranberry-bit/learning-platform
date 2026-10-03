<?php

namespace App\Modules\Content\Application\Contracts;

interface AcceptedCandidateWriterInterface
{
    /** @return array{lexemes:int, grammar:int} */
    public function apply(int $runId, int $contentId, string $translationLanguage, \Closure $onGrammarRuleCreated): array;
}
