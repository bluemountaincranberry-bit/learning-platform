<?php

namespace App\Modules\Ai\Application;

use App\Contracts\Ai\LexemeEnrichmentCapability;
use App\Modules\Content\Application\Contracts\LexemeEnrichmentCatalogInterface;
use Illuminate\Support\Str;

/**
 * Orchestrates two callers of the same AI proposal: the admin-triggered
 * "AI: Enrich" action on a canonical Lexeme, and (task 10.4)
 * EnrichLexemeAssociationsJob, which persists a proposal automatically for
 * every newly created lexeme. propose() only calls AI and matches against
 * the existing catalog — it writes nothing, mirroring AiFieldEditService's
 * "AI never saves directly" rule. applyAccepted() is the only method that
 * persists, and only for whatever the caller explicitly marked accepted
 * (an admin's review, or the job's "accept the top N automatically" policy).
 */
class LexemeEnrichmentService implements LexemeEnrichmentCapability
{
    public function __construct(
        private readonly AiFieldEditService $aiFieldEditService,
        private readonly CandidateMatchingService $matchingService,
        private readonly LexemeEnrichmentCatalogInterface $catalog,
    ) {}

    public function enrichTopRelated(int $lexemeId, int $maxRelated): void
    {
        $context = $this->catalog->promptContext($lexemeId);
        if ($context === null) {
            return;
        }

        $proposal = $this->proposeWithContext($context);
        $related = collect($proposal['related'])
            ->take($maxRelated)
            ->map(fn (array $row): array => [...$row, 'accept' => true])
            ->all();

        $this->catalog->applyAccepted($lexemeId, ['related' => $related]);
    }

    /**
     * @return array{
     *     related: array<int, array{lemma: string, type: string, gloss: ?string, matched_lexeme_id: ?int, match_score: ?float}>,
     *     examples: array<int, array{example: string, translation: ?string}>,
     *     translations: array<int, array{language: string, translation: string}>,
     * }
     */
    public function propose(int $lexemeId): array
    {
        $context = $this->catalog->promptContext($lexemeId);
        if ($context === null) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException;
        }

        return $this->proposeWithContext($context);
    }

    private function proposeWithContext(array $context): array
    {
        $proposal = $this->aiFieldEditService->proposePrepared(
            $context['prompt'],
            $context['subject_type'],
            $context['subject_id'],
        );
        $language = $context['language'];
        $translationLanguage = config('ai.analysis.translation_language', 'ru');

        $related = collect($proposal['related'] ?? [])
            ->filter(fn ($row) => filled($row['lemma'] ?? null))
            ->map(function (array $row) use ($language, $context): array {
                $lemma = trim((string) $row['lemma']);
                $match = $this->matchingService->findBestLexemeMatch(Str::lower($lemma), $lemma, $language);
                $type = is_string($row['type'] ?? null) && in_array($row['type'], $context['relation_types'], true)
                    ? $row['type']
                    : 'related';

                return [
                    'lemma' => $lemma,
                    'type' => $type,
                    'gloss' => $row['gloss'] ?? null,
                    'matched_lexeme_id' => $match['lexeme_id'],
                    'match_score' => $match['score'],
                ];
            })
            ->values()
            ->all();

        $examples = collect($proposal['examples'] ?? [])
            ->filter(fn ($row) => filled($row['example'] ?? null))
            ->map(fn (array $row): array => [
                'example' => $row['example'],
                'translation' => $row['translation'] ?? null,
            ])
            ->values()
            ->all();

        $translations = filled($proposal['translation'] ?? null)
            ? [['language' => $translationLanguage, 'translation' => $proposal['translation']]]
            : [];

        return [
            'related' => $related,
            'examples' => $examples,
            'translations' => $translations,
        ];
    }

    /**
     * @param  array{related?: array<int, array{lemma: string, type?: string, matched_lexeme_id: ?int, accept?: bool}>, examples?: array<int, array{example: string, translation: ?string, accept?: bool}>, translations?: array<int, array{language: string, translation: string, accept?: bool}>}  $data
     * @return array{related: int, examples: int, translations: int}
     */
    public function applyAccepted(int $lexemeId, array $data): array
    {
        return $this->catalog->applyAccepted($lexemeId, $data);
    }
}
