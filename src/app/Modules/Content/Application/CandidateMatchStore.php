<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\CandidateMatchStoreInterface;
use App\Modules\Content\Domain\GrammarRuleTitle;
use App\Modules\Content\Domain\Models\ContentGrammarCandidate;
use App\Modules\Content\Domain\Models\ContentLexemeCandidate;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\Lexeme;

class CandidateMatchStore implements CandidateMatchStoreInterface
{
    public function lexemeCandidates(int $runId): array
    {
        return ContentLexemeCandidate::query()->where('ai_analysis_run_id', $runId)->get()
            ->map(fn (ContentLexemeCandidate $candidate): array => [
                'id' => (int) $candidate->id,
                'normalized_lemma' => $candidate->normalized_lemma,
                'normalized_text' => (string) $candidate->normalized_text,
                'lemma' => $candidate->lemma,
                'text' => (string) $candidate->text,
            ])->all();
    }

    public function grammarCandidates(int $runId): array
    {
        return ContentGrammarCandidate::query()->where('ai_analysis_run_id', $runId)->get()
            ->map(fn (ContentGrammarCandidate $candidate): array => [
                'id' => (int) $candidate->id,
                'title' => (string) $candidate->title,
                'summary' => $candidate->summary,
            ])->all();
    }

    public function exactLexemeId(string $normalizedLemma, string $language): ?int
    {
        return Lexeme::query()->where('language', $language)
            ->where('normalized_lemma', $normalizedLemma)->value('id');
    }

    public function exactGrammarRuleId(string $title, string $language): ?int
    {
        $normalized = GrammarRuleTitle::normalize($title);
        if ($normalized === '') {
            return null;
        }

        $id = GrammarRule::query()
            ->where('language', $language)
            ->where('normalized_title', $normalized)
            ->where('status', '!=', GrammarRule::STATUS_ARCHIVED)
            ->orderBy('id')
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    public function lexemeIdsForLanguage(string $language): array
    {
        return Lexeme::query()->where('language', $language)->pluck('id')->all();
    }

    public function updateLexemeMatch(int $candidateId, ?int $lexemeId, ?float $score): void
    {
        ContentLexemeCandidate::query()->whereKey($candidateId)->update([
            'matched_lexeme_id' => $lexemeId,
            'match_score' => $score,
        ]);
    }

    public function updateGrammarMatch(int $candidateId, ?int $ruleId, ?float $score): void
    {
        ContentGrammarCandidate::query()->whereKey($candidateId)->update([
            'matched_grammar_rule_id' => $ruleId,
            'match_score' => $score,
        ]);
    }
}
