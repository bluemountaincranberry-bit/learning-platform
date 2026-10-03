<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\CandidateAnalysisStoreInterface;
use App\Modules\Content\Domain\Models\ContentGrammarCandidate;
use App\Modules\Content\Domain\Models\ContentLexemeCandidate;

class CandidateAnalysisStore implements CandidateAnalysisStoreInterface
{
    public function createLexeme(int $runId, array $attributes): void
    {
        $type = $attributes['type'] ?? null;
        $attributes['type'] = is_string($type) && in_array($type, ContentLexemeCandidate::TYPES, true)
            ? $type
            : ContentLexemeCandidate::TYPE_WORD;
        $attributes['status'] = ContentLexemeCandidate::STATUS_PENDING;

        ContentLexemeCandidate::query()->create(['ai_analysis_run_id' => $runId, ...$attributes]);
    }

    public function createGrammar(int $runId, array $attributes): void
    {
        ContentGrammarCandidate::query()->create([
            'ai_analysis_run_id' => $runId,
            ...$attributes,
            'status' => ContentGrammarCandidate::STATUS_PENDING,
        ]);
    }

    public function normalizedLexemeTexts(int $runId): array
    {
        return ContentLexemeCandidate::query()
            ->where('ai_analysis_run_id', $runId)
            ->pluck('normalized_text')
            ->all();
    }

    public function listingForRun(int $runId): array
    {
        return [
            'lexemes' => ContentLexemeCandidate::query()
                ->where('ai_analysis_run_id', $runId)
                ->get(['id', 'text', 'type', 'translation', 'level', 'confidence', 'matched_lexeme_id', 'status'])
                ->toArray(),
            'grammar' => ContentGrammarCandidate::query()
                ->where('ai_analysis_run_id', $runId)
                ->get(['id', 'title', 'summary', 'confidence', 'matched_grammar_rule_id', 'status'])
                ->toArray(),
        ];
    }

    public function deleteForRun(int $runId): void
    {
        ContentLexemeCandidate::query()->where('ai_analysis_run_id', $runId)->delete();
        ContentGrammarCandidate::query()->where('ai_analysis_run_id', $runId)->delete();
    }
}
