<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\ManualLexemeCandidateStoreInterface;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\ContentLexemeCandidate;
use Illuminate\Support\Str;

final class ManualLexemeCandidateStore implements ManualLexemeCandidateStoreInterface
{
    public function create(int $runId, string $text, array $analysis): int
    {
        $candidate = ContentLexemeCandidate::query()->create([
            'ai_analysis_run_id' => $runId,
            'text' => $text,
            'normalized_text' => Str::lower($text),
            'lemma' => $analysis['lemma'],
            'normalized_lemma' => Str::lower($analysis['lemma']),
            'type' => str_contains($text, ' ') ? ContentLexemeCandidate::TYPE_PHRASE : ContentLexemeCandidate::TYPE_WORD,
            'part_of_speech' => $analysis['part_of_speech'],
            'sense' => $analysis['sense'],
            'grammar_features' => $analysis['grammar_features'],
            'level' => $analysis['level'],
            'translation' => $analysis['translation'],
            'example' => $analysis['example'],
            'example_translation' => $analysis['example_translation'],
            'examples' => [[
                'text' => $analysis['example'],
                'translation' => $analysis['example_translation'],
                'source' => 'context',
            ]],
            'confidence' => 1.0,
            'status' => ContentLexemeCandidate::STATUS_PENDING,
        ]);

        return $candidate->id;
    }

    public function accept(int $candidateId): void
    {
        ContentLexemeCandidate::query()->findOrFail($candidateId)
            ->update(['status' => ContentLexemeCandidate::STATUS_ACCEPTED]);
    }

    public function markManualOccurrence(int $contentId, string $text): int
    {
        $occurrence = ContentLexeme::query()
            ->where('content_id', $contentId)
            ->where('text', $text)
            ->latest('id')
            ->firstOrFail();
        $occurrence->update(['origin' => ContentLexeme::ORIGIN_MANUAL]);

        return $occurrence->id;
    }
}
