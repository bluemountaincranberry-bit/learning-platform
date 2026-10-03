<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\CandidateModerationInterface;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentGrammarCandidate;
use App\Modules\Content\Domain\Models\ContentLexemeCandidate;

class CandidateModeration implements CandidateModerationInterface
{
    public function acceptEligibleForRun(int $runId, string $sourceText, ?string $contentLevel, array $config): void
    {
        $pending = ContentLexemeCandidate::query()
            ->where('ai_analysis_run_id', $runId)
            ->where('status', ContentLexemeCandidate::STATUS_PENDING)
            ->get();

        foreach ($pending as $candidate) {
            $candidate->update([
                'status' => $this->isValidLexemeCandidate($candidate, $sourceText, $contentLevel, $config)
                    ? ContentLexemeCandidate::STATUS_ACCEPTED
                    : ContentLexemeCandidate::STATUS_REJECTED,
            ]);
        }

        ContentGrammarCandidate::query()
            ->where('ai_analysis_run_id', $runId)
            ->where('status', ContentGrammarCandidate::STATUS_PENDING)
            ->update(['status' => ContentGrammarCandidate::STATUS_ACCEPTED]);
    }

    private function isValidLexemeCandidate(ContentLexemeCandidate $candidate, string $sourceText, ?string $contentLevel, array $config): bool
    {
        $text = trim((string) $candidate->text);
        $translation = trim((string) $candidate->translation);
        $confidence = $candidate->confidence;
        $level = strtoupper(trim((string) $candidate->level));
        $translationLanguage = strtolower(trim((string) ($config['translation_language'] ?? config('ai.analysis.translation_language', 'ru'))));

        if ($text === '' || $translation === '' || ! is_numeric($confidence)) {
            return false;
        }

        if ($translationLanguage === 'ru' && preg_match('/\p{Cyrillic}/u', $translation) !== 1) {
            return false;
        }

        if ($confidence < (float) config('ai.analysis.min_lexeme_confidence', 0.7)
            || ! in_array($level, Content::CEFR_LEVELS, true)
            || ($candidate->type === ContentLexemeCandidate::TYPE_WORD && mb_strlen($text) < 2)
        ) {
            return false;
        }

        if (preg_match('/https?:\/\/|www\.|@/iu', $text) === 1
            || str_contains($text, '/')
            || str_contains($text, '\\')
            || preg_match('/^\d+(?:[.,]\d+)?$/u', $text) === 1
            || preg_match('/\d/u', $text) === 1
            || preg_match('/[^\p{L}\s\'’\-]/u', $text) === 1
            || preg_match('/\p{L}/u', $text) !== 1
        ) {
            return false;
        }

        $transcript = mb_strtolower(str_replace('’', "'", $sourceText));
        $normalizedText = mb_strtolower(str_replace('’', "'", $text));
        $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($normalizedText, '/').'(?!(?:[\p{L}\p{N}]))/u';

        if (preg_match($pattern, $transcript) !== 1) {
            return false;
        }

        $targetLevel = strtoupper(trim((string) ($config['target_level'] ?? $contentLevel ?? '')));
        if ($targetLevel === '') {
            return true;
        }

        $targetIndex = array_search($targetLevel, Content::CEFR_LEVELS, true);
        $candidateIndex = array_search($level, Content::CEFR_LEVELS, true);

        return $targetIndex !== false
            && $candidateIndex !== false
            && $candidateIndex >= max(0, $targetIndex - 1);
    }
}
