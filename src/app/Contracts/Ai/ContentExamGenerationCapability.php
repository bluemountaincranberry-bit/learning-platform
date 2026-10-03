<?php

namespace App\Contracts\Ai;

interface ContentExamGenerationCapability
{
    /** @return list<array<string, mixed>> */
    public function generateContentExam(int $userId, int $contentId, int $count): array;

    /**
     * @param  list<int>  $grammarRuleIds
     * @return list<array<string, mixed>>
     */
    public function generateGrammarWarmup(int $userId, int $contentId, array $grammarRuleIds, int $count): array;
}
